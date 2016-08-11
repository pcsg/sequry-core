<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Actors\CryptoGroup
 */

namespace Pcsg\GroupPasswordManager\Actors;

use Monolog\Handler\Curl\Util;
use ParagonIE\Halite\Symmetric\Crypto;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Authentication\Plugin;
use Pcsg\GroupPasswordManager\Security\Authentication\SecurityClass;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\Keys\AuthKeyPair;
use Pcsg\GroupPasswordManager\Security\Keys\Key;
use Pcsg\GroupPasswordManager\Security\Keys\KeyPair;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\SecretSharing;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use QUI;
use Pcsg\GroupPasswordManager\Constants\Tables;
use Symfony\Component\Console\Helper\Table;

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
     * Group key pair
     *
     * @var KeyPair
     */
    protected $KeyPair = null;

    /**
     * SecurityClass this groups is associated to
     *
     * @var SecurityClass
     */
    protected $SecurityClass = null;

    /**
     * CryptoGroup constructor.
     *
     * @param integer $groupId - quiqqer group id
     */
    public function __construct($groupId)
    {
        parent::__construct($groupId);

        $this->KeyPair       = $this->getKeyPair();
        $this->SecurityClass = $this->getSecurityClass();
    }

    /**
     * Return Key pair for specific authentication plugin (private key is ENCRYPTED)
     *
     * @return KeyPair
     * @throws QUI\Exception
     */
    public function getKeyPair()
    {
        if (!is_null($this->KeyPair)) {
            return $this->KeyPair;
        }

        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::KEYPAIRS_GROUP,
            'where' => array(
                'groupId' => $this->getId()
            ),
            'limit' => 1
        ));

        if (empty($result)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
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
        $MACActual   = MAC::create(implode('', $integrityData), Utils::getSystemKeyPairAuthKey());

        if (!MAC::compare($MACActual, $MACExpected)) {
            QUI\System\Log::addCritical(
                'Group key pair #' . $data['id'] . ' possibly altered. MAC mismatch!'
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptogroup.keypair.not.authentic',
                array(
                    'groupId' => $this->getId()
                )
            ));
        }

        $this->KeyPair = new KeyPair($data['publicKey'], $data['privateKey']);

        return $this->KeyPair;
    }

    /**
     * Get Group KeyPair with DECRYPTED private key
     *
     * @param CryptoUser $DecryptUser (optional) - The user whose authentication information is used to decrypt
     * the group private key; if omitted use session user
     * @return KeyPair
     *
     * @throws QUI\Exception
     */
    public function getKeyPairDecrypted($DecryptUser = null)
    {
        if (is_null($DecryptUser)) {
            $DecryptUser = CryptoActors::getCryptoUser();
        }

        // decrypt group access key
        $GroupPrivateKeyDecrypted = new Key(
            SymmetricCrypto::decrypt(
                $this->KeyPair->getPrivateKey()->getValue(),
                $DecryptUser->getGroupAccessKey($this)
            )
        );

        $GroupKeyPairDecrypted = new KeyPair(
            $this->KeyPair->getPublicKey()->getValue(),
            $GroupPrivateKeyDecrypted->getValue()
        );

        return $GroupKeyPairDecrypted;
    }

    /**
     * Return SecurityClass that is associated with this group
     *
     * @return SecurityClass
     */
    public function getSecurityClass()
    {
        return Authentication::getSecurityClass($this->getSecurityClassId());
    }

    /**
     * Return ID of SecurityClass that is associated with this group
     *
     * @return integer
     */
    public function getSecurityClassId()
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'securityClassId'
            ),
            'from'   => Tables::KEYPAIRS_GROUP,
            'where'  => array(
                'groupId' => $this->getId()
            )
        ));

        return (int)$result[0]['securityClassId'];
    }

    /**
     * Set security class for this group
     *
     * @param SecurityClass $SecurityClass
     * @return void
     *
     * @throws QUI\Exception
     */
    public function addSecurityClass($SecurityClass)
    {
        if (!$SecurityClass->checkGroupUsersForEligibility($this)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptogroup.setsecurityclass.users.not.eligible',
                array(
                    'groupId'         => $this->getId(),
                    'securityClassId' => $SecurityClass->getId()
                )
            ));
        }

        // check if session user has group access
        if (!$this->hasCryptoUserAccess()) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptogroup.setsecurityclass.users.has.no.group.access',
                array(
                    'groupId'         => $this->getId(),
                    'securityClassId' => $SecurityClass->getId(),
                    'userId'          => QUI::getUserBySession()->getId()
                )
            ));
        }

        // get (decrypted) group keypair
        $CryptoSessionUser = CryptoActors::getCryptoUser();
        $groupAccessKey    = $CryptoSessionUser->getGroupAccessKey($this)->getValue();

        // split group access key according to new security class
        $groupAccessKeyParts = SecretSharing::splitSecret(
            $groupAccessKey,
            $SecurityClass->getAuthPluginCount(),
            $SecurityClass->getRequiredFactors()
        );

        $users = $this->getCryptoUsers();
        $DB    = QUI::getDataBase();

        /** @var CryptoUser $CryptoUser */
        foreach ($users as $CryptoUser) {
            $authKeyPairs = $CryptoUser->getAuthKeyPairsBySecurityClass($SecurityClass);
            $i            = 0;

            // delete old access entries for group
            $DB->delete(
                Tables::USER_TO_GROUPS,
                array(
                    'userId'  => $CryptoUser->getId(),
                    'groupId' => $this->getId()
                )
            );

            /** @var AuthKeyPair $AuthKeyPair */
            foreach ($authKeyPairs as $AuthKeyPair) {
                $privateKeyEncryptionKeyPartEncrypted = AsymmetricCrypto::encrypt(
                    $groupAccessKeyParts[$i++],
                    $AuthKeyPair
                );

                $data = array(
                    'userId'        => $CryptoUser->getId(),
                    'userKeyPairId' => $AuthKeyPair->getId(),
                    'groupId'       => $this->getId(),
                    'groupKey'      => $privateKeyEncryptionKeyPartEncrypted
                );

                // calculate MAC
                $data['MAC'] = MAC::create(implode('', $data), Utils::getSystemKeyPairAuthKey());

                $DB->insert(Tables::USER_TO_GROUPS, $data);
            }
        }

        // update cryptogroup key entry and calculate new MAC
        $result = $DB->fetch(array(
            'from'  => Tables::KEYPAIRS_GROUP,
            'where' => array(
                'groupId' => $this->getId()
            )
        ));

        $data                    = $result[0];
        $data['securityClassId'] = $SecurityClass->getId();

        $MACData = array(
            $data['groupId'],
            $data['securityClassId'],
            $data['publicKey'],
            $data['privateKey']
        );

        $data['MAC'] = MAC::create(
            implode('', $MACData),
            Utils::getSystemKeyPairAuthKey()
        );

        $DB->inser(
            Tables::KEYPAIRS_GROUP,
            $data,
            array(
                'groupId' => $this->getId()
            )
        );
    }

    /**
     * Adds a user to this group so he can access all passwords the group has access to
     *
     * @param CryptoUser $AddUser - The user that is added to the group
     * @param CryptoUser $CryptoUser - The user that adds $AddUser to the group; if omitted, use session user
     * @return void
     *
     * @throws QUI\Exception
     */
    public function addCryptoUser($AddUser, $CryptoUser = null)
    {
        if (is_null($CryptoUser)) {
            $CryptoUser = CryptoActors::getCryptoUser();
        }

        if (!$this->SecurityClass->isUserEligible($AddUser)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptogroup.add.user.not.eligible',
                array(
                    'userId'          => $AddUser->getId(),
                    'groupId'         => $this->getId(),
                    'securityClassId' => $this->SecurityClass->getId()
                )
            ));
        }

        // split key
        // @todo PERMISSION

        $GroupAccessKey = $CryptoUser->getGroupAccessKey($this);

        $groupAccessKeyParts = SecretSharing::splitSecret(
            $GroupAccessKey->getValue(),
            $this->SecurityClass->getAuthPluginCount(),
            $this->SecurityClass->getRequiredFactors()
        );

        // encrypt key parts with user public keys
        $i            = 0;
        $authKeyPairs = $AddUser->getAuthKeyPairsBySecurityClass($this->SecurityClass);

        /** @var AuthKeyPair $UserAuthKeyPair */
        foreach ($authKeyPairs as $UserAuthKeyPair) {
            try {
                $payloadKeyPart = $groupAccessKeyParts[$i++];

                $groupAccessKeyPartEncrypted = AsymmetricCrypto::encrypt(
                    $payloadKeyPart,
                    $UserAuthKeyPair
                );

                $data = array(
                    'userId'        => $AddUser->getId(),
                    'userKeyPairId' => $UserAuthKeyPair->getId(),
                    'groupId'       => $this->getId(),
                    'groupKey'      => $groupAccessKeyPartEncrypted
                );

                // calculate MAC
                $data['MAC'] = MAC::create(implode('', $data), Utils::getSystemKeyPairAuthKey());

                QUI::getDataBase()->insert(Tables::USER_TO_GROUPS, $data);
            } catch (\Exception $Exception) {
                QUI\System\Log::addError(
                    'Error writing password key parts to database: ' . $Exception->getMessage()
                );

                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptogroup.add.user.general.error',
                    array(
                        'userId'  => $AddUser->getId(),
                        'groupId' => $this->getId(),
                    )
                ));
            }
        }
    }

    /**
     * Remove access to group for crypto user
     *
     * @param CryptoUser $CryptoUser
     * @return void
     *
     * @throws QUI\Exception
     */
    public function removeCryptoUser($CryptoUser)
    {
        if (!$this->hasCryptoUserAccess($CryptoUser)) {
            return;
        }

        $userCount = (int)$this->countUser();

        \QUI\System\Log::writeRecursive($userCount);

        // if the user that is to be removed is the last user of this group,
        // the user cannot be deleted
        if ($userCount === 1) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptogroup.cannot.remove.last.user',
                array(
                    'userId'          => $CryptoUser->getId(),
                    'groupId'         => $this->getId(),
                    'securityClassId' => $this->SecurityClass->getId()
                )
            ));
        }

        QUI::getDataBase()->delete(
            Tables::USER_TO_GROUPS,
            array(
                'userId'  => $CryptoUser->getId(),
                'groupId' => $this->getId()
            )
        );
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
            'from'   => Tables::USER_TO_GROUPS,
            'where'  => array(
                'groupId' => $this->getId()
            )
        ));

        foreach ($result as $row) {
            $userIds[] = $row['userId'];
        }

        return $userIds;
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
     * @return array
     */
    public function getPasswordIds()
    {
        $passwordIds = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'dataId'
            ),
            'from'   => Tables::GROUP_TO_PASSWORDS,
            'where'  => array(
                'groupId' => $this->getId()
            )
        ));

        foreach ($result as $row) {
            $passwordIds[] = $row['dataId'];
        }

        return $passwordIds;
    }

    /**
     * Decrypt group private key with current session user
     *
     * @return Key - (decrypted) private key
     */
    protected function decryptGroupPrivateKey()
    {
        $CryptoUser = CryptoActors::getCryptoUser();


    }
}