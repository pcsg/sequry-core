<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Password
 */

namespace Pcsg\GroupPasswordManager\Security\Handler;

use Pcsg\GroupPasswordManager\Actors\CryptoGroup;
use Pcsg\GroupPasswordManager\Actors\CryptoUser;
use Pcsg\GroupPasswordManager\Security\Authentication\SecurityClass;
use QUI;
use Pcsg\GroupPasswordManager\Constants\Tables;

/**
 * Class for for managing system actors - users and groups
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class CryptoActors
{
    /**
     * Crypto users
     *
     * @var array
     */
    protected static $users = array();

    /**
     * Crypto groups
     *
     * @var array
     */
    protected static $groups = array();

    /**
     * Get crypto user
     *
     * @param integer $id (optional) - QUIQQER User ID; if omitted use session user
     * @return CryptoUser
     */
    public static function getCryptoUser($id = null)
    {
        if (is_null($id)) {
            $User = QUI::getUserBySession();
        } else {
            $User = QUI::getUsers()->get($id);
        }

        $userId = $User->getId();

        if (isset(self::$users[$userId])) {
            return self::$users[$userId];
        }

        self::$users[$userId] = new CryptoUser($userId);

        return self::$users[$userId];
    }

    /**
     * Creates a CryptoGroup out of a standard QUIQQER Group, so it can be used
     * in the password management system
     *
     * @param QUI\Groups\Group $Group
     * @param SecurityClass $SecurityClass - The security class that determines how the group key will be encrypted
     * @return CryptoGroup
     *
     * @throws QUI\Exception
     */
    public static function createCryptoGroup($Group, $SecurityClass)
    {
        // check if group is associated with any other security class
        $result = QUI::getDataBase()->fetch(array(
            'count' => 1,
            'from'  => Tables::KEYPAIRS_GROUP,
            'where' => array(
                'groupId' => $Group->getId()
            )
        ));

        if (current(current($result)) > 0) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptoactors.addcryptogroup.otherwise.associated',
                array(
                    'groupId'         => $Group->getId(),
                    'securityClassId' => $SecurityClass->getId()
                )
            ));
        }

        $users = $Group->getUsers();

        if (empty($users)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.addcryptogroup.no.users',
                array(
                    'groupId'         => $Group->getId(),
                    'securityClassId' => $SecurityClass->getId()
                )
            ));
        }

        if (!$this->checkGroupUsersForEligibility($Group)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.addcryptogroup.users.not.eligible',
                array(
                    'groupId'         => $Group->getId(),
                    'securityClassId' => $this->getId()
                )
            ));
        }

        // generate key pair and encrypt
        $authPlugins = $this->getAuthPlugins();

        $GroupKeyPair    = AsymmetricCrypto::generateKeyPair();
        $publicGroupKey  = $GroupKeyPair->getPublicKey()->getValue();
        $privateGroupKey = $GroupKeyPair->getPrivateKey()->getValue();

        $PrivateKeyEncryptionKey = SymmetricCrypto::generateKey();

        $privateGroupKeyEncrypted = SymmetricCrypto::encrypt(
            $privateGroupKey,
            $PrivateKeyEncryptionKey
        );

        // insert group key data into database
        $DB = QUI::getDataBase();

        $data = array(
            'groupId'         => $Group->getId(),
            'securityClassId' => $this->getId(),
            'publicKey'       => $publicGroupKey,
            'privateKey'      => $privateGroupKeyEncrypted
        );

        // calculate group key MAC
        $data['MAC'] = MAC::create(implode('', $data), Utils::getSystemKeyPairAuthKey());

        $DB->insert(Tables::KEYPAIRS_GROUP, $data);

        // split group private key encryption key into parts and share with group users
        $privateKeyEncryptionKeyParts = SecretSharing::splitSecret(
            $PrivateKeyEncryptionKey->getValue(),
            count($authPlugins),
            $this->requiredFactors
        );

        foreach ($users as $userData) {
            $User        = CryptoActors::getCryptoUser($userData['id']);
            $authPlugins = $User->getAuthPluginsBySecurityClass($this);
            $i           = 0;

            /** @var Plugin $AuthPlugin */
            foreach ($authPlugins as $AuthPlugin) {
                $AuthKeyPair                          = $User->getAuthKeyPair($AuthPlugin);
                $privateKeyEncryptionKeyPartEncrypted = AsymmetricCrypto::encrypt(
                    $privateKeyEncryptionKeyParts[$i++],
                    $AuthKeyPair
                );

                $data = array(
                    'userId'        => $User->getId(),
                    'userKeyPairId' => $AuthKeyPair->getId(),
                    'groupId'       => $Group->getId(),
                    'groupKey'      => $privateKeyEncryptionKeyPartEncrypted
                );

                // calculate MAC
                $data['MAC'] = MAC::create(implode('', $data), Utils::getSystemKeyPairAuthKey());

                $DB->insert(Tables::USER_TO_GROUPS, $data);
            }
        }
    }

    /**
     * Get crypto user
     *
     * @param integer $id - QUIQQER Group ID
     * @return CryptoGroup
     */
    public static function getCryptoGroup($id)
    {
        if (isset(self::$groups[$id])) {
            return self::$groups[$id];
        }

        self::$groups[$id] = new CryptoGroup($id);

        return self::$groups[$id];
    }
}