<?php

/**
 * This file contains \Sequry\Core\Actors\CryptoGroup
 */

namespace Sequry\Core\Actors;

use Sequry\Core\Constants\Permissions;
use Sequry\Core\Events;
use Sequry\Core\Password;
use Sequry\Core\Security\AsymmetricCrypto;
use Sequry\Core\Security\Authentication\SecurityClass;
use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Security\Handler\Passwords;
use Sequry\Core\Security\HiddenString;
use Sequry\Core\Security\Keys\AuthKeyPair;
use Sequry\Core\Security\Keys\KeyPair;
use Sequry\Core\Security\MAC;
use Sequry\Core\Security\SecretSharing;
use Sequry\Core\Security\SymmetricCrypto;
use Sequry\Core\Security\Utils;
use QUI;
use QUI\Permissions\Permission as QUIPermissions;
use Sequry\Core\Constants\Tables;

/**
 * Group Class
 *
 * Represents a password manager Group that can retrieve encrypted passwords
 * if the necessary permission are given.
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class CryptoGroup extends QUI\Groups\Group
{
    /**
     * Group key pairs for different security classes
     *
     * @var KeyPair
     */
    protected $keyPairs = null;

    /**
     * CryptoUser that interacts with this group
     *
     * @var CryptoUser
     */
    protected $CryptoUser = null;

    /**
     * CryptoGroup constructor.
     *
     * @param integer $groupId - quiqqer group id
     */
    public function __construct($groupId)
    {
        parent::__construct($groupId);

        $this->CryptoUser = CryptoActors::getCryptoUser();   // session user
    }

    /**
     * Return Key pair for group for specific security class (private key is ENCRYPTED)
     *
     * @param SecurityClass $SecurityClass
     * @return KeyPair
     * @throws QUI\Exception
     */
    public function getKeyPair($SecurityClass)
    {
        if (isset($this->keyPairs[$SecurityClass->getId()])
            && !is_null($this->keyPairs[$SecurityClass->getId()])
        ) {
            return $this->keyPairs[$SecurityClass->getId()];
        }

        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::keyPairsGroup(),
            'where' => array(
                'groupId'         => $this->getId(),
                'securityClassId' => $SecurityClass->getId()
            ),
            'limit' => 1
        ));

        if (empty($result)) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptogroup.keypair.not.found',
                array(
                    'groupId' => $this->getId()
                )
            ));
        }

        $data = current($result);

        // check keypair integrity
        $integrityData = array(
            $data['groupId'],
            $data['securityClassId'],
            $data['publicKey'],
            $data['privateKey']
        );

        $MACExpected = $data['MAC'];
        $MACActual   = MAC::create(
            new HiddenString(implode('', $integrityData)),
            Utils::getSystemKeyPairAuthKey()
        );

        if (!MAC::compare($MACActual, $MACExpected)) {
            QUI\System\Log::addCritical(
                'Group key pair #' . $data['id'] . ' possibly altered. MAC mismatch!'
            );

            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptogroup.keypair.not.authentic',
                array(
                    'groupId' => $this->getId()
                )
            ));
        }

        $this->keyPairs[$SecurityClass->getId()] = new KeyPair(
            new HiddenString($data['publicKey']),
            new HiddenString($data['privateKey'])
        );

        return $this->keyPairs[$SecurityClass->getId()];
    }

    /**
     * Return SecurityClass that is associated with this group
     *
     * @return array
     */
    public function getSecurityClasses()
    {
        $securityClassIds = $this->getSecurityClassIds();
        $securityClasses  = array();

        foreach ($securityClassIds as $securityClassId) {
            $securityClasses[] = Authentication::getSecurityClass($securityClassId);
        }

        return $securityClasses;
    }

    /**
     * Return IDs of SecurityClass that are associated with this group
     *
     * @return array
     */
    public function getSecurityClassIds()
    {
        $securityClassIds = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'securityClassId'
            ),
            'from'   => Tables::keyPairsGroup(),
            'where'  => array(
                'groupId' => $this->getId()
            )
        ));

        foreach ($result as $row) {
            $securityClassIds[] = $row['securityClassId'];
        }

        return $securityClassIds;
    }

    /**
     * Checks if this group has a specific security class assigned
     *
     * @param SecurityClass $SecurityClass
     * @return bool
     */
    public function hasSecurityClass($SecurityClass)
    {
        return in_array($SecurityClass->getId(), $this->getSecurityClassIds());
    }

    /**
     * Create a SecurityClass key for this group
     *
     * @param SecurityClass $SecurityClass
     * @param QUI\Users\User $User (optional) - Initial User that gets access to the group key
     * (requires eligibility for given $SecurityClass) [default: Session user]
     * @return CryptoGroup
     *
     * @throws QUI\Exception
     */
    public function addSecurityClass($SecurityClass, $User = null)
    {
        $this->checkCryptoUserPermission();

        // check if security class is already set to this group
        if (in_array($SecurityClass->getId(), $this->getSecurityClassIds())) {
            return;
        }

        if (!$SecurityClass->checkGroupUsersForEligibility($this)) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptogroup.addsecurityclass.users.not.eligible',
                array(
                    'groupId'            => $this->getId(),
                    'groupName'          => $this->getAttribute('name'),
                    'securityClassId'    => $SecurityClass->getId(),
                    'securityClassTitle' => $SecurityClass->getAttribute('title')
                )
            ));
        }

        // generate new key pair and encrypt
        $GroupKeyPair    = AsymmetricCrypto::generateKeyPair();
        $publicGroupKey  = $GroupKeyPair->getPublicKey()->getValue();
        $privateGroupKey = $GroupKeyPair->getPrivateKey()->getValue();

        $GroupAccessKey = SymmetricCrypto::generateKey();

        $privateGroupKeyEncrypted = SymmetricCrypto::encrypt(
            $privateGroupKey,
            $GroupAccessKey
        );

        // insert group key data into database
        $DB = QUI::getDataBase();

        $data = array(
            'groupId'         => $this->getId(),
            'securityClassId' => $SecurityClass->getId(),
            'publicKey'       => $publicGroupKey,
            'privateKey'      => $privateGroupKeyEncrypted
        );

        // calculate group key MAC
        $data['MAC'] = MAC::create(
            new HiddenString(implode('', $data)),
            Utils::getSystemKeyPairAuthKey()
        );

        $DB->insert(Tables::keyPairsGroup(), $data);

        // split group access key into parts and share with group users
        $groupAccessKeyParts = SecretSharing::splitSecret(
            $GroupAccessKey->getValue(),
            $SecurityClass->getAuthPluginCount(),
            $SecurityClass->getRequiredFactors()
        );

        $users = $this->getCryptoUsers();

        /** @var CryptoUser $User */
        foreach ($users as $User) {
            $authKeyPairs = $User->getAuthKeyPairsBySecurityClass($SecurityClass);
            $i            = 0;

            /** @var AuthKeyPair $AuthKeyPair */
            foreach ($authKeyPairs as $AuthKeyPair) {
                $privateKeyEncryptionKeyPartEncrypted = AsymmetricCrypto::encrypt(
                    new HiddenString($groupAccessKeyParts[$i++]),
                    $AuthKeyPair
                );

                $data = array(
                    'userId'          => $User->getId(),
                    'userKeyPairId'   => $AuthKeyPair->getId(),
                    'securityClassId' => $SecurityClass->getId(),
                    'groupId'         => $this->getId(),
                    'groupKey'        => $privateKeyEncryptionKeyPartEncrypted
                );

                // calculate MAC
                $data['MAC'] = MAC::create(
                    new HiddenString(implode('', $data)),
                    Utils::getSystemKeyPairAuthKey()
                );

                $DB->insert(Tables::usersToGroups(), $data);
            }
        }
    }

    /**
     * Remove security class from group
     *
     * @param SecurityClass $SecurityClass
     *
     * @throws QUI\Exception
     */
    public function removeSecurityClass(SecurityClass $SecurityClass)
    {
        if (!$this->CryptoUser->isSU()) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptogroup.removesecurityclass.no.permission'
            ));
        }

        if (!$this->hasSecurityClass($SecurityClass)) {
            return;
        }

        $DB = QUI::getDataBase();

        // delete all passwords of security class this group owns
        $ownerPasswordIds = $this->getOwnerPasswordIds($SecurityClass);

        foreach ($ownerPasswordIds as $passwordId) {
            $Password = Passwords::get($passwordId);
            $Password->delete();
        }

        // delete all password access data of passwords with security class
        $securityClassPasswordIds = $SecurityClass->getPasswordIds();

        if (!empty($securityClassPasswordIds)) {
            $DB->delete(
                Tables::groupsToPasswords(),
                array(
                    'groupId' => $this->getId(),
                    'dataId'  => array(
                        'type'  => 'IN',
                        'value' => $securityClassPasswordIds
                    )
                )
            );
        }

        // delete the group key pair for this security class
        $DB->delete(
            Tables::keyPairsGroup(),
            array(
                'groupId'         => $this->getId(),
                'securityClassId' => $SecurityClass->getId()
            )
        );

        // delete all access data of users to this group with this security class
        $DB->delete(
            Tables::usersToGroups(),
            array(
                'groupId'         => $this->getId(),
                'securityClassId' => $SecurityClass->getId()
            )
        );
    }

    /**
     * Checks if a user meets the requirements to join this group
     *
     * @param CryptoUser $AddUser
     * @return bool
     */
    public function canUserBeAdded(CryptoUser $AddUser)
    {
        if ($this->hasCryptoUserAccess($AddUser)) {
            return false;
        }

        // check if user is eligible for all security classes
        $securityClasses = $this->getSecurityClasses();

        /** @var SecurityClass $SecurityClass */
        foreach ($securityClasses as $SecurityClass) {
            if (!$SecurityClass->isUserEligible($AddUser)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Adds a user to this group so he can access all passwords the group has access to
     *
     * @param CryptoUser $AddUser - The user that is added to the group
     * @return void
     *
     * @throws QUI\Exception
     */
    public function addCryptoUser(CryptoUser $AddUser)
    {
        if ($this->hasCryptoUserAccess($AddUser)) {
            return;
        }

        if ((int)$AddUser->getId() === (int)QUI::getUserBySession()->getId()) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptogroup.add.user.cannot.add.himself'
            ));
        }

        $this->checkCryptoUserPermission();

        // check if user is eligible for all security classes
        $securityClasses = $this->getSecurityClasses();

        /** @var SecurityClass $SecurityClass */
        foreach ($securityClasses as $SecurityClass) {
            if (!$SecurityClass->isUserEligible($AddUser)) {
                throw new QUI\Exception(array(
                    'sequry/core',
                    'exception.cryptogroup.add.user.not.eligible',
                    array(
                        'userId'          => $AddUser->getId(),
                        'groupId'         => $this->getId(),
                        'securityClassId' => $SecurityClass->getId()
                    )
                ));
            }
        }

        // split group keys
        foreach ($securityClasses as $SecurityClass) {
            // split key
            $GroupAccessKey = $this->CryptoUser->getGroupAccessKey($this, $SecurityClass);

            $groupAccessKeyParts = SecretSharing::splitSecret(
                $GroupAccessKey->getValue(),
                $SecurityClass->getAuthPluginCount(),
                $SecurityClass->getRequiredFactors()
            );

            // encrypt key parts with user public keys
            $i            = 0;
            $authKeyPairs = $AddUser->getAuthKeyPairsBySecurityClass($SecurityClass);

            /** @var AuthKeyPair $UserAuthKeyPair */
            foreach ($authKeyPairs as $UserAuthKeyPair) {
                try {
                    $payloadKeyPart = $groupAccessKeyParts[$i++];

                    $groupAccessKeyPartEncrypted = AsymmetricCrypto::encrypt(
                        new HiddenString($payloadKeyPart),
                        $UserAuthKeyPair
                    );

                    $data = array(
                        'userId'          => $AddUser->getId(),
                        'userKeyPairId'   => $UserAuthKeyPair->getId(),
                        'securityClassId' => $SecurityClass->getId(),
                        'groupId'         => $this->getId(),
                        'groupKey'        => $groupAccessKeyPartEncrypted
                    );

                    // calculate MAC
                    $data['MAC'] = MAC::create(
                        new HiddenString(implode('', $data)),
                        Utils::getSystemKeyPairAuthKey()
                    );

                    QUI::getDataBase()->insert(Tables::usersToGroups(), $data);
                } catch (\Exception $Exception) {
                    QUI\System\Log::addError(
                        'Error writing group key parts to database: ' . $Exception->getMessage()
                    );

                    QUI::getDataBase()->delete(
                        Tables::usersToGroups(),
                        array(
                            'userId'          => $AddUser->getId(),
                            'groupId'         => $this->getId(),
                            'securityClassId' => $SecurityClass->getId()
                        )
                    );

                    throw new QUI\Exception(array(
                        'sequry/core',
                        'exception.cryptogroup.add.user.general.error',
                        array(
                            'userId'  => $AddUser->getId(),
                            'groupId' => $this->getId()
                        )
                    ));
                }
            }
        }

        // create meta table entries
        $AddUser->refreshPasswordMetaTableEntries();
    }

    /**
     * Remove access to group for crypto user
     *
     * @param CryptoUser $RemoveUser - the user that is removed
     * @return void
     *
     * @throws QUI\Exception
     */
    public function removeCryptoUser(CryptoUser $RemoveUser)
    {
        // SU can always remove users from groups
        if (!$this->CryptoUser->isSU()) {
            $this->checkCryptoUserPermission();
        }

        if (!$this->hasCryptoUserAccess($RemoveUser)) {
            return;
        }

        $userCount = (int)$this->countUser();

        // if the user that is to be removed is the last user of this group,
        // the user cannot be deleted
        if ($userCount <= 1) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptogroup.cannot.remove.last.user',
                array(
                    'userId'  => $RemoveUser->getId(),
                    'groupId' => $this->getId()
                )
            ));
        }

        QUI::getDataBase()->delete(
            Tables::usersToGroups(),
            array(
                'userId'  => $RemoveUser->getId(),
                'groupId' => $this->getId()
            )
        );

        // delete meta table entries
        $RemoveUser->refreshPasswordMetaTableEntries();
    }

    /**
     * Return all CryptoUsers that belong to this CryptoGroup
     *
     * @return array - CryptoUser objects
     */
    public function getCryptoUsers()
    {
        $userIds = $this->getCryptoUserIds();
        $users   = array();

        foreach ($userIds as $userId) {
            $users[] = CryptoActors::getCryptoUser($userId);
        }

        return $users;
    }

    /**
     * Return IDs of all user that have access to this CryptoGroup
     *
     * @return array
     */
    public function getCryptoUserIds()
    {
        $userIds = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'userId'
            ),
            'from'   => Tables::usersToGroups(),
            'where'  => array(
                'groupId' => $this->getId()
            )
        ));

        foreach ($result as $row) {
            $userIds[] = $row['userId'];
        }

        return array_unique($userIds);
    }

    /**
     * Checks if a user has access to this group
     *
     * @param CryptoUser $User (optional) - if omitted use session user
     *
     * @return bool
     */
    public function hasCryptoUserAccess($User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        $userIds = $this->getCryptoUserIds();

        return in_array($User->getId(), $userIds);
    }

    /**
     * Get IDs of all passwords this group has access to
     *
     * @param SecurityClass $SecurityClass (optional) - only passwords of specific security class
     *
     * @return array
     */
    public function getPasswordIds($SecurityClass = null)
    {
        $passwordIds = array();
        $where       = array(
            'groupId' => $this->getId()
        );

        if (!is_null($SecurityClass)) {
            $securityClassPasswordIds = $SecurityClass->getPasswordIds();

            if (!empty($securityClassPasswordIds)) {
                $where['dataId'] = array(
                    'type'  => 'IN',
                    'value' => $securityClassPasswordIds
                );
            }
        }

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'dataId'
            ),
            'from'   => Tables::groupsToPasswords(),
            'where'  => $where
        ));

        foreach ($result as $row) {
            $passwordIds[] = $row['dataId'];
        }

        return $passwordIds;
    }

    /**
     * Get all passwords the group has access to
     *
     * @param SecurityClass $SecurityClass
     * @return array
     */
    public function getPasswords(SecurityClass $SecurityClass = null)
    {
        $passwords = array();

        foreach ($this->getPasswordIds($SecurityClass) as $passwordId) {
            $passwords[] = Passwords::get($passwordId);
        }

        return $passwords;
    }

    /**
     * Get IDs of all password this group owns
     *
     * @param SecurityClass $SecurityClass (optional) - only passwords of specific security class
     *
     * @return array
     */
    public function getOwnerPasswordIds($SecurityClass = null)
    {
        $passwordIds = array();
        $where       = array(
            'ownerId'   => $this->getId(),
            'ownerType' => Password::OWNER_TYPE_GROUP
        );

        if (!is_null($SecurityClass)) {
            $where['securityClassId'] = $SecurityClass->getId();
        }

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => Tables::passwords(),
            'where'  => $where
        ));

        foreach ($result as $row) {
            $passwordIds[] = $row['id'];
        }

        return $passwordIds;
    }

    /**
     * Checks if the current Group CryptoUser is part of this group AND has permission to edit it
     *
     * @return void
     * @throws QUI\Exception
     */
    protected function checkCryptoUserPermission()
    {
        if (!$this->hasCryptoUserAccess($this->CryptoUser)) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptogroup.no.group.access',
                array(
                    'groupId'   => $this->getId(),
                    'groupName' => $this->getAttribute('name')
                )
            ));
        }

        if (!QUIPermissions::hasPermission(Permissions::GROUP_EDIT, $this->CryptoUser)) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptogroup.no.permission'
            ));
        };
    }

    /**
     * Takes a password access key and re-encrypts it with the current
     * key pair according to the security class of the password
     *
     * @param integer $passwordId - password ID
     * @return void
     * @throws QUI\Exception
     */
    public function reEncryptPasswordAccessKey($passwordId)
    {
        $accessPasswordIdsAccess = $this->getPasswordIds();

        if (!in_array($passwordId, $accessPasswordIdsAccess)) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptogroup.reencryptpasswordaccesskey.no.access',
                array(
                    'groupId'    => $this->getId(),
                    'groupName'  => $this->getAttribute('name'),
                    'passwordId' => $passwordId
                )
            ));
        }

        $Password      = Passwords::get($passwordId);
        $PasswordKey   = $Password->getPasswordKey();
        $SecurityClass = $Password->getSecurityClass();

        if (!$SecurityClass->isGroupEligible($this)) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptogroup.reencryptpasswordaccesskey.securityclass.not.eligible',
                array(
                    'groupId'            => $this->getId(),
                    'groupName'          => $this->getAttribute('name'),
                    'passwordId'         => $passwordId,
                    'securityClassId'    => $SecurityClass->getId(),
                    'securityClassTitle' => $SecurityClass->getAttribute('title')
                )
            ));
        }

        // split key
        $KeyPair = $this->getKeyPair($SecurityClass);
        $DB      = QUI::getDataBase();

        try {
            // delete old access entry
            $DB->delete(
                Tables::groupsToPasswords(),
                array(
                    'groupId' => $this->getId(),
                    'dataId'  => $passwordId
                )
            );

            $encryptedPasswordKeyValue = AsymmetricCrypto::encrypt(
                $PasswordKey->getValue(),
                $KeyPair
            );

            $dataAccessEntry = array(
                'groupId' => $this->getId(),
                'dataId'  => $passwordId,
                'dataKey' => $encryptedPasswordKeyValue
            );

            $dataAccessEntry['MAC'] = MAC::create(
                new HiddenString(implode('', $dataAccessEntry)),
                Utils::getSystemKeyPairAuthKey()
            );

            $DB->insert(
                Tables::groupsToPasswords(),
                $dataAccessEntry
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'CryptoGroup :: reEncryptPasswordAccessKey() :: Error writing password key parts to database: '
                . $Exception->getMessage()
            );

            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptogroup.reencryptpasswordaccesskey.general.error',
                array(
                    'groupId'    => $this->getId(),
                    'groupName'  => $this->getAttribute('name'),
                    'passwordId' => $passwordId,
                )
            ));
        }
    }

    /**
     * Irrevocably delete group and all passwords owned by it
     *
     * @return void
     * @throws QUI\Exception
     */
    public function delete()
    {
        // groups can only be deleted by super users
        $SessionUser = QUI::getUserBySession();

        if (!$SessionUser->isSU()) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptogroup.delete.no.permission'
            ));
        }

        $DB = QUI::getDataBase();

        // delete all passwords this group owns
        $ownerPasswordIds = $this->getOwnerPasswordIds();

        foreach ($ownerPasswordIds as $passwordId) {
            $Password = Passwords::get($passwordId);
            $Password->delete();
        }

        // delete all password access data
        $DB->delete(
            Tables::groupsToPasswords(),
            array(
                'groupId' => $this->getId()
            )
        );

        // delete all key pairs
        $DB->delete(
            Tables::keyPairsGroup(),
            array(
                'groupId' => $this->getId()
            )
        );

        // delete all access data of users to this group
        $DB->delete(
            Tables::usersToGroups(),
            array(
                'groupId' => $this->getId()
            )
        );

        Events::$triggerGroupDeleteConfirm = false;

        parent::delete();
    }
}
