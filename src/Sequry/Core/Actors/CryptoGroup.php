<?php

/**
 * This file contains \Sequry\Core\Actors\CryptoGroup
 */

namespace Sequry\Core\Actors;

use Sequry\Core\Constants\Permissions;
use Sequry\Core\Events;
use Sequry\Core\Exception\Exception;
use Sequry\Core\Exception\PermissionDeniedException;
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

        $result = QUI::getDataBase()->fetch([
            'from'  => Tables::keyPairsGroup(),
            'where' => [
                'groupId'         => $this->getId(),
                'securityClassId' => $SecurityClass->getId()
            ],
            'limit' => 1
        ]);

        if (empty($result)) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.cryptogroup.keypair.not.found',
                [
                    'groupId' => $this->getId()
                ]
            ]);
        }

        $data = current($result);

        // check keypair integrity
        $integrityData = [
            $data['groupId'],
            $data['securityClassId'],
            $data['publicKey'],
            $data['privateKey']
        ];

        $MACExpected = $data['MAC'];
        $MACActual   = MAC::create(
            new HiddenString(implode('', $integrityData)),
            Utils::getSystemKeyPairAuthKey()
        );

        if (!MAC::compare($MACActual, $MACExpected)) {
            QUI\System\Log::addCritical(
                'Group key pair #'.$data['id'].' possibly altered. MAC mismatch!'
            );

            throw new QUI\Exception([
                'sequry/core',
                'exception.cryptogroup.keypair.not.authentic',
                [
                    'groupId' => $this->getId()
                ]
            ]);
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
     * @return SecurityClass[]
     * @throws QUI\Exception
     */
    public function getSecurityClasses()
    {
        $securityClassIds = $this->getSecurityClassIds();
        $securityClasses  = [];

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
        $securityClassIds = [];

        $result = QUI::getDataBase()->fetch([
            'select' => [
                'securityClassId'
            ],
            'from'   => Tables::keyPairsGroup(),
            'where'  => [
                'groupId' => $this->getId()
            ]
        ]);

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
    public function hasSecurityClass(SecurityClass $SecurityClass)
    {
        return in_array($SecurityClass->getId(), $this->getSecurityClassIds());
    }

    /**
     * Create a SecurityClass key for this group
     *
     * @param SecurityClass $SecurityClass
     * @return void
     *
     * @throws \Exception
     */
    public function addSecurityClass(SecurityClass $SecurityClass)
    {
        if (!QUIPermissions::hasPermission(Permissions::GROUP_CREATE, $this->CryptoUser)) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.cryptogroup.no.permission'
            ]);
        };

        // check if security class is already set to this group
        if (in_array($SecurityClass->getId(), $this->getSecurityClassIds())) {
            return;
        }

        // check if all admin users are eligible for the SecurityClass
        /** @var CryptoUser $AdminUser */
        $uneligibleUsers = array();
        $adminUsers      = $this->getAdminUsers();

        if (empty($adminUsers)) {
            throw new Exception(array(
                'sequry/core',
                'exception.cryptoactors.createCryptoGroupKey.group_has_no_admins',
                array(
                    'groupId' => $this->getId()
                )
            ));
        }

        foreach ($adminUsers as $AdminUser) {
            if (!$SecurityClass->isUserEligible($AdminUser)) {
                $uneligibleUsers[] = $AdminUser->getUsername();
            }
        }

        if (!empty($uneligibleUsers)) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptogroup.add_securityclass.not_all_admins_eligible',
                array(
                    'groupId'         => $this->getId(),
                    'securityClassId' => $SecurityClass->getId(),
                    'users'           => implode(', ', $uneligibleUsers)
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

        $data = [
            'groupId'         => $this->getId(),
            'securityClassId' => $SecurityClass->getId(),
            'publicKey'       => $publicGroupKey,
            'privateKey'      => $privateGroupKeyEncrypted
        ];

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

        /** @var CryptoUser $User */
        foreach ($this->getUsers() as $user) {
            $CryptoUser = CryptoActors::getCryptoUser($user['id']);

//        foreach ($this->getCryptoUsers() as $User) {
            $this->writeUserAccessEntry($CryptoUser, $SecurityClass, $groupAccessKeyParts);

            // create meta table entries
            $CryptoUser->refreshPasswordMetaTableEntries();
        }
    }

    /**
     * Write an access entry for a user to this group to the Database
     *
     * @param CryptoUser $User - The User that shall have access to the group
     * @param SecurityClass $SecurityClass - The security class the access entry is for
     * @param array $groupAccessKeyParts - All parts of the splitted Group Access Key
     * @return void
     * @throws \Exception
     */
    protected function writeUserAccessEntry(CryptoUser $User, SecurityClass $SecurityClass, $groupAccessKeyParts)
    {
        $DB  = QUI::getDataBase();
        $tbl = Tables::usersToGroups();

        // get existing entries
        $result = $DB->fetch([
            'select' => [
                'userKeyPairId',
                'groupKey'
            ],
            'from'   => Tables::usersToGroups(),
            'where'  => [
                'userId'          => $User->getId(),
                'groupId'         => $this->getId(),
                'securityClassId' => $SecurityClass->getId()
            ]
        ]);

        $access = [];

        foreach ($result as $row) {
            if (!empty($row['userKeyPairId'])) {
                $access[$row['userKeyPairId']] = !empty($row['groupKey']);
            }
        }

        try {
            // create empty entries if user is not eligible (yet) for SecurityClass
            if (!$SecurityClass->isUserEligible($User)) {
                foreach ($SecurityClass->getAuthPlugins() as $AuthPlugin) {
                    try {
                        $AuthKeyPair = $User->getAuthKeyPair($AuthPlugin);
                        $keyPairId   = $AuthKeyPair->getId();
                    } catch (\Exception $Exception) {
                        $keyPairId = null;
                    }

                    $data = [
                        'userId'          => $User->getId(),
                        'userKeyPairId'   => $keyPairId,
                        'securityClassId' => $SecurityClass->getId(),
                        'groupId'         => $this->getId(),
                        'groupKey'        => null
                    ];

                    // calculate MAC
                    $data['MAC'] = MAC::create(
                        new HiddenString(implode('', $data)),
                        Utils::getSystemKeyPairAuthKey()
                    );

                    $DB->insert($tbl, $data);
                }

                return;
            }

            $authKeyPairs = $User->getAuthKeyPairsBySecurityClass($SecurityClass);
            $i            = 0;

            /** @var AuthKeyPair $AuthKeyPair */
            foreach ($authKeyPairs as $AuthKeyPair) {
                $privateKeyEncryptionKeyPartEncrypted = AsymmetricCrypto::encrypt(
                    new HiddenString($groupAccessKeyParts[$i++]),
                    $AuthKeyPair
                );

                $data = [
                    'userId'          => $User->getId(),
                    'userKeyPairId'   => $AuthKeyPair->getId(),
                    'securityClassId' => $SecurityClass->getId(),
                    'groupId'         => $this->getId(),
                    'groupKey'        => $privateKeyEncryptionKeyPartEncrypted
                ];

                // calculate MAC
                $data['MAC'] = MAC::create(
                    new HiddenString(implode('', $data)),
                    Utils::getSystemKeyPairAuthKey()
                );

                $DB->insert($tbl, $data);
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Error writing group key parts to database.'
            );

            QUI\System\Log::writeException($Exception);

            QUI::getDataBase()->delete(
                Tables::usersToGroups(),
                [
                    'userId'          => $User->getId(),
                    'groupId'         => $this->getId(),
                    'securityClassId' => $SecurityClass->getId()
                ]
            );

            throw $Exception;
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
            throw new QUI\Exception([
                'sequry/core',
                'exception.cryptogroup.removesecurityclass.no.permission'
            ]);
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
                [
                    'groupId' => $this->getId(),
                    'dataId'  => [
                        'type'  => 'IN',
                        'value' => $securityClassPasswordIds
                    ]
                ]
            );
        }

        // delete the group key pair for this security class
        $DB->delete(
            Tables::keyPairsGroup(),
            [
                'groupId'         => $this->getId(),
                'securityClassId' => $SecurityClass->getId()
            ]
        );

        // delete all access data of users to this group with this security class
        $DB->delete(
            Tables::usersToGroups(),
            [
                'groupId'         => $this->getId(),
                'securityClassId' => $SecurityClass->getId()
            ]
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
        if ($this->isUserInGroup($AddUser)) {
            return false;
        }

        return true;
    }

    /**
     * Adds a user to this group so he can access all passwords the group has access to
     *
     * @param CryptoUser $AddUser - The user that is added to the group
     * @param SecurityClass $AddSequrityClass (optional) - Write access entry only for a specific SecurityClass
     * @return void
     *
     * @throws QUI\Exception
     */
    public function addCryptoUser(CryptoUser $AddUser, $AddSequrityClass = null)
    {
        // permission check
        $this->checkAdminPermission();

        if ((int)$AddUser->getId() === (int)QUI::getUserBySession()->getId()) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.cryptogroup.add.user.cannot.add.himself'
            ]);
        }

        if ($this->isUserInGroup($AddUser)) {
            $this->removeCryptoUser($AddUser);
        }

        $securityClasses = $this->getSecurityClasses();

        // split group keys
        foreach ($securityClasses as $SecurityClass) {
            if (!is_null($AddSequrityClass)
                && $AddSequrityClass->getId() !== $SecurityClass->getId()) {
                continue;
            }

            // split key
            $GroupAccessKey = $this->CryptoUser->getGroupAccessKey($this, $SecurityClass);

            $groupAccessKeyParts = SecretSharing::splitSecret(
                $GroupAccessKey->getValue(),
                $SecurityClass->getAuthPluginCount(),
                $SecurityClass->getRequiredFactors()
            );

            try {
                $this->writeUserAccessEntry($AddUser, $SecurityClass, $groupAccessKeyParts);
            } catch (\Exception $Exception) {
                throw new QUI\Exception([
                    'sequry/core',
                    'exception.cryptogroup.add.user.general.error',
                    [
                        'userId'  => $AddUser->getId(),
                        'groupId' => $this->getId()
                    ]
                ]);
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
        // if the user that is to be removed is an admin of this group
        // -> try to remove admin status first
        if ($this->isAdminUser($RemoveUser)) {
            $this->removeAdminUser($RemoveUser);
        }

        // SU can always remove users from groups
        if (!$this->CryptoUser->isSU()) {
            $this->checkAdminPermission();
        }

        if (!$this->isUserInGroup($RemoveUser)) {
            return;
        }

        $userCount = (int)$this->countUser();

        // if the user that is to be removed is the last user of this group,
        // the user cannot be deleted
        if ($userCount <= 1) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.cryptogroup.cannot.remove.last.user',
                [
                    'userId'  => $RemoveUser->getId(),
                    'groupId' => $this->getId()
                ]
            ]);
        }

        QUI::getDataBase()->delete(
            Tables::usersToGroups(),
            [
                'userId'  => $RemoveUser->getId(),
                'groupId' => $this->getId()
            ]
        );

        // delete meta table entries
        $RemoveUser->refreshPasswordMetaTableEntries();
    }

    /**
     * Add a User with administration privileges for this CryptoGroup
     *
     * Group admins are able to:
     * - Add users to the group
     * - Remove users from the group
     * - Retrospectively grant access to users to group passwords for a specific SecurityClass
     * - Always edit, share and delete passwords the group owns regardless of permissions
     *
     * @param CryptoUser $User
     * @param bool $checkIfUserIsInGroup (optional) - Checks if the user is in the group
     * This parameter is only used if the group user is added asynchronously via the QUIQQER event system
     * to prevent recursive user-to-group-adding
     * @return void
     * @throws PermissionDeniedException
     * @throws QUI\Exception
     */
    public function addAdminUser(CryptoUser $User, $checkIfUserIsInGroup = true)
    {
        $this->checkAdminManagePermission();

        if ($this->isAdminUser($User)) {
            return;
        }

        // Create regular access for Admin User
        $Session      = QUI::getSession();
        $sessionCache = $Session->get('add_adminusers_to_group');
        $groupId      = $this->getId();

        if (empty($sessionCache)) {
            $sessionCache = [];
        }

        if (empty($sessionCache[$groupId])) {
            $sessionCache[$groupId] = [];
        }

        if ($checkIfUserIsInGroup && !$this->isUserInGroup($User)) {
            $this->addUser($User);

            $sessionCache[$groupId][] = $User->getId();
            QUI::getSession()->set('add_adminusers_to_group', $sessionCache);

            $User->save(QUI::getUsers()->getSystemUser());
        }

        // Admin users have to be eligible for all SecurityClasses of this Group
        foreach ($this->getSecurityClasses() as $SecurityClass) {
            if (!$SecurityClass->isUserEligible($User)) {
                throw new Exception([
                    'sequry/core',
                    'exception.actors.cryptogroup.admin_user_not_eligible',
                    [
                        'username' => $User->getUsername(),
                        'userId'   => $User->getId()
                    ]
                ]);
            }
        }

        $data = [
            'groupId' => $this->getId(),
            'userId'  => $User->getId()
        ];

        $data['MAC'] = MAC::create(
            new HiddenString(implode('', $data)),
            Utils::getSystemKeyPairAuthKey()
        );

        QUI::getDataBase()->insert(
            Tables::groupAdmins(),
            $data
        );

        if (in_array($User->getId(), $sessionCache[$groupId])) {
            unset($sessionCache[$groupId][array_search($User->getId(), $sessionCache[$groupId])]);
        }

        QUI::getSession()->set('add_adminusers_to_group', $sessionCache);
    }

    /**
     * Remove a user as an admin of this group (does NOT remove generall access to the group!)
     *
     * @param CryptoUser $User
     * @return void
     * @throws Exception
     */
    public function removeAdminUser(CryptoUser $User)
    {
        $this->checkAdminManagePermission();

        if (!$this->isAdminUser($User)) {
            return;
        }

        $adminUserCount     = $this->getAdminUserCount();
        $securityClassCount = count($this->getSecurityClassIds());

        if ($adminUserCount === 1 && $securityClassCount > 0) {
            throw new Exception([
                'sequry/core',
                'exception.actors.cryptogroup.admin_user_required'
            ]);
        }

        QUI::getDataBase()->delete(
            Tables::groupAdmins(),
            [
                'groupId' => $this->getId(),
                'userId'  => $User->getId()
            ]
        );
    }

    /**
     * Check if a CryptoUser is a group admin
     *
     * @param CryptoUser $User
     * @return bool
     */
    public function isAdminUser(CryptoUser $User)
    {
        $result = QUI::getDataBase()->fetch([
            'count' => 1,
            'from'  => Tables::groupAdmins(),
            'where' => [
                'groupId' => $this->getId(),
                'userId'  => $User->getId()
            ]
        ]);

        return (int)current(current($result)) > 0;
    }

    /**
     * Get IDs of Group administrator users
     *
     * @return array
     */
    public function getAdminUserIds()
    {
        $result = QUI::getDataBase()->fetch([
            'select' => 'userId',
            'from'   => Tables::groupAdmins(),
            'where'  => [
                'groupId' => $this->getId()
            ]
        ]);

        $ids = [];

        foreach ($result as $row) {
            $ids[] = (int)$row['userId'];
        }

        return $ids;
    }

    /**
     * Get all admins of this group
     *
     * @return CryptoUser[]
     */
    public function getAdminUsers()
    {
        $users = [];

        foreach ($this->getAdminUserIds() as $userId) {
            $users[] = CryptoActors::getCryptoUser($userId);
        }

        return $users;
    }

    /**
     * Get number of admin users for this CryptoGroup
     *
     * @return int
     */
    protected function getAdminUserCount()
    {
        $result = QUI::getDataBase()->fetch([
            'count' => 1,
            'from'  => Tables::groupAdmins(),
            'where' => [
                'groupId' => $this->getId()
            ]
        ]);

        return (int)current(current($result));
    }

    /**
     * Return all CryptoUsers that belong to this CryptoGroup
     *
     * @return array - CryptoUser objects
     */
    public function getCryptoUsers()
    {
        $userIds = $this->getCryptoUserIds();
        $users   = [];

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
        $userIds = [];

        $result = QUI::getDataBase()->fetch([
            'select' => [
                'userId'
            ],
            'from'   => Tables::usersToGroups(),
            'where'  => [
                'groupId' => $this->getId()
            ]
        ]);

        foreach ($result as $row) {
            $userIds[] = $row['userId'];
        }

        return array_unique($userIds);
    }

    /**
     * Checks if a CryptoUser is a member of this CryptoGroup
     *
     * @param CryptoUser $User (optional) - if omitted use session user
     *
     * @return bool
     */
    public function isUserInGroup($User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        $userIds = $this->getCryptoUserIds();
        return in_array($User->getId(), $userIds);
    }

    /**
     * Checks if a User has access to this group for a specific SecurityClass
     *
     * @param SecurityClass $SecurityClass
     * @param CryptoUser $User (optional) - if omitted use session user
     * @return bool
     */
    public function hasCryptoUserAccess(SecurityClass $SecurityClass, $User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        if (!$this->isUserInGroup($User)) {
            return false;
        }

        if (!$SecurityClass->isUserEligible($User)) {
            return false;
        }

        $result = QUI::getDataBase()->fetch([
            'count' => 1,
            'from'  => Tables::usersToGroups(),
            'where' => [
                'userId'          => $User->getId(),
                'groupId'         => $this->getId(),
                'securityClassId' => $SecurityClass->getId(),
                'userKeyPairId'   => [
                    'type'  => 'NOT',
                    'value' => null
                ],
                'groupKey'        => [
                    'type'  => 'NOT',
                    'value' => null
                ]
            ]
        ]);

        $accessKeyPartsCount = (int)current(current($result));

        if ($accessKeyPartsCount < $SecurityClass->getRequiredFactors()) {
            return false;
        }

        return true;
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
        $passwordIds = [];
        $where       = [
            'groupId' => $this->getId()
        ];

        if (!is_null($SecurityClass)) {
            $securityClassPasswordIds = $SecurityClass->getPasswordIds();

            if (!empty($securityClassPasswordIds)) {
                $where['dataId'] = [
                    'type'  => 'IN',
                    'value' => $securityClassPasswordIds
                ];
            }
        }

        $result = QUI::getDataBase()->fetch([
            'select' => [
                'dataId'
            ],
            'from'   => Tables::groupsToPasswords(),
            'where'  => $where
        ]);

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
        $passwords = [];

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
        $passwordIds = [];
        $where       = [
            'ownerId'   => $this->getId(),
            'ownerType' => Password::OWNER_TYPE_GROUP
        ];

        if (!is_null($SecurityClass)) {
            $where['securityClassId'] = $SecurityClass->getId();
        }

        $result = QUI::getDataBase()->fetch([
            'select' => [
                'id'
            ],
            'from'   => Tables::passwords(),
            'where'  => $where
        ]);

        foreach ($result as $row) {
            $passwordIds[] = $row['id'];
        }

        return $passwordIds;
    }

    /**
     * Get IDs of users that are part of this group but are
     * not yet unlocked for all group SecurityClasses
     *
     * @return int[]
     */
    public function getNoAccessUserIds()
    {
        $result = QUI::getDataBase()->fetch([
            'select' => [
                'userId'
            ],
            'from'   => Tables::usersToGroups(),
            'where'  => [
                'userKeyPairId' => [
                    'type'  => 'NOT',
                    'value' => null
                ],
                'groupKey'      => null,
                'groupId'       => $this->getId()
            ]
        ]);

        $userIds = [];

        foreach ($result as $row) {
            $userIds[] = (int)$row['userId'];
        }

        return $userIds;
    }

    /**
     * Checks if the current Group CryptoUser is part of this group AND has permission to edit it
     *
     * @return void
     * @throws PermissionDeniedException
     */
    protected function checkAdminPermission()
    {
        if (!$this->isAdminUser($this->CryptoUser)) {
            throw new PermissionDeniedException([
                'sequry/core',
                'exception.cryptogroup.no_admin_permission',
                [
                    'groupId'   => $this->getId(),
                    'groupName' => $this->getAttribute('name')
                ]
            ]);
        }

//        if (!QUIPermissions::hasPermission(Permissions::GROUP_CREATE, $this->CryptoUser)) {
//            throw new PermissionDeniedException([
//                'sequry/core',
//                'exception.cryptogroup.no.permission'
//            ]);
//        };
    }

    /**
     * Check if the current user has the permission to manage group admins
     *
     * @throws PermissionDeniedException
     */
    protected function checkAdminManagePermission()
    {
        if (!QUIPermissions::hasPermission(Permissions::GROUP_MANAGE_ADMINS, $this->CryptoUser)) {
            throw new PermissionDeniedException([
                'sequry/core',
                'exception.cryptogroup.no.permission'
            ]);
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
            throw new QUI\Exception([
                'sequry/core',
                'exception.cryptogroup.reencryptpasswordaccesskey.no.access',
                [
                    'groupId'    => $this->getId(),
                    'groupName'  => $this->getAttribute('name'),
                    'passwordId' => $passwordId
                ]
            ]);
        }

        $Password      = Passwords::get($passwordId);
        $PasswordKey   = $Password->getPasswordKey();
        $SecurityClass = $Password->getSecurityClass();

        if (!$SecurityClass->isGroupEligible($this)) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.cryptogroup.reencryptpasswordaccesskey.securityclass.not.eligible',
                [
                    'groupId'            => $this->getId(),
                    'groupName'          => $this->getAttribute('name'),
                    'passwordId'         => $passwordId,
                    'securityClassId'    => $SecurityClass->getId(),
                    'securityClassTitle' => $SecurityClass->getAttribute('title')
                ]
            ]);
        }

        // split key
        $KeyPair = $this->getKeyPair($SecurityClass);
        $DB      = QUI::getDataBase();

        try {
            // delete old access entry
            $DB->delete(
                Tables::groupsToPasswords(),
                [
                    'groupId' => $this->getId(),
                    'dataId'  => $passwordId
                ]
            );

            $encryptedPasswordKeyValue = AsymmetricCrypto::encrypt(
                $PasswordKey->getValue(),
                $KeyPair
            );

            $dataAccessEntry = [
                'groupId' => $this->getId(),
                'dataId'  => $passwordId,
                'dataKey' => $encryptedPasswordKeyValue
            ];

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
                .$Exception->getMessage()
            );

            throw new QUI\Exception([
                'sequry/core',
                'exception.cryptogroup.reencryptpasswordaccesskey.general.error',
                [
                    'groupId'    => $this->getId(),
                    'groupName'  => $this->getAttribute('name'),
                    'passwordId' => $passwordId,
                ]
            ]);
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
            throw new QUI\Exception([
                'sequry/core',
                'exception.cryptogroup.delete.no.permission'
            ]);
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
            [
                'groupId' => $this->getId()
            ]
        );

        // delete all key pairs
        $DB->delete(
            Tables::keyPairsGroup(),
            [
                'groupId' => $this->getId()
            ]
        );

        // delete all access data of users to this group
        $DB->delete(
            Tables::usersToGroups(),
            [
                'groupId' => $this->getId()
            ]
        );

        Events::$triggerGroupDeleteConfirm = false;

        parent::delete();
    }
}
