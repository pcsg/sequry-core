<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Security\Handler\Passwords
 */

namespace Pcsg\GroupPasswordManager\Security\Handler;

use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\CryptoUser;
use Pcsg\GroupPasswordManager\Password;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Authentication\Plugin;
use Pcsg\GroupPasswordManager\Security\Authentication\SecurityClass;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\SecretSharing;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use QUI;

/**
 * Class for for managing passwords
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Passwords
{
    /**
     * Password objects
     *
     * @var array
     */
    protected static $passwords = array();

    /**
     * Create a new password
     *
     * @param array $passwordData - password data
     * @return null
     * @throws QUI\Exception
     */
    public static function createPassword($passwordData)
    {
        // check if necessary data is given
        if (empty($passwordData)
            || !is_array($passwordData)
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwords.create.missing.data'
            ));
        }

        // security class check
        if (!isset($passwordData['securityClassId'])
            || empty($passwordData['securityClassId'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwords.create.missing.securityclass'
            ));
        }

        $SecurityClass = Authentication::getSecurityClass(
            $passwordData['securityClassId']
        );

        // owner check
        if (!isset($passwordData['owner'])
            || empty($passwordData['owner'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwords.create.missing.owner'
            ));
        }

        $owner = $passwordData['owner'];

        if (!isset($owner['id'])
            || empty($owner['id'])
            || !isset($owner['type'])
            || empty($owner['type'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwords.create.owner.incorrect.data'
            ));
        }

        $ownerUsers = array();
        $ownerId    = (int)$owner['id'];
        $groupId    = false;

        switch ($owner['type']) {
            case 'user':
                try {
                    $User = CryptoActors::getCryptoUser($ownerId);

                    if (!$SecurityClass->isUserEligible($User)) {
                        throw new QUI\Exception();
                    }

                    $ownerUsers[] = $User;

                } catch (QUI\Exception $Exception) {
                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.passwords.create.owner.user.incorrect.data'
                    ));
                }
                break;

            case 'group':
                $groupId = $ownerId;
                $Group   = QUI::getGroups()->get($groupId);

                if (!$SecurityClass->isGroupEligible($Group)) {
                    throw new QUI\Exception();
                }

                $result = $Group->getUsers(array(
                    'select' => array(
                        'id'
                    )
                ));

                foreach ($result as $row) {
                    $ownerUsers[] = CryptoActors::getCryptoUser($row['id']);
                }
                break;

            default:
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.passwords.create.owner.group.incorrect.data'
                ));
        }

        // title check
        if (!isset($passwordData['title'])
            || empty($passwordData['title'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwords.create.missing.title'
            ));
        }

        // payload check
        if (!isset($passwordData['payload'])
            || empty($passwordData['payload'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwords.create.missing.payload'
            ));
        }

        // set initial content for password
        $passwordContent = array(
            'ownerId'    => $ownerId,
            'ownerType'  => $owner['type'],
            'payload'    => $passwordData['payload'],
            'sharedWith' => array(),
            'history'    => array()
        );

        // generate password key
        $PayloadKey = SymmetricCrypto::generateKey();

        // encrypt password data and calculate MAC
        $passwordContentEncrypted = SymmetricCrypto::encrypt(
            json_encode($passwordContent), $PayloadKey
        );

        $passwordEntry = array(
            'securityClassId' => $SecurityClass->getId(),
            'title'           => $passwordData['title'],
            'description'     => $passwordData['description'],
            'cryptoData'      => $passwordContentEncrypted
        );

        $passwordEntry['MAC'] = MAC::create(
            implode('', $passwordEntry),
            Utils::getSystemPasswordAuthKey()
        );

        // write to database
        $DB = QUI::getDataBase();

        try {
            $DB->insert(
                Tables::PASSWORDS,
                $passwordEntry
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Error writing password to database: ' . $Exception->getMessage()
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.create.general.error'
            ));
        }

        $passwordId = QUI::getPDO()->lastInsertId();

        // split key
        $authPlugins = $SecurityClass->getAuthPlugins();

        $payloadKeyParts = SecretSharing::splitSecret(
            $PayloadKey->getValue(),
            count($authPlugins)
        );

        // encrypt key parts with owner public keys
        /** @var CryptoUser $User */
        foreach ($ownerUsers as $User) {
            $i = 0;

            /** @var Plugin $Plugin */
            foreach ($authPlugins as $Plugin) {
                try {
                    $UserAuthKeyPair = $User->getAuthKeyPair($Plugin);
                    $payloadKeyPart  = $payloadKeyParts[$i++];

                    $encryptedPayloadKeyPart = AsymmetricCrypto::encrypt(
                        $payloadKeyPart, $UserAuthKeyPair
                    );

                    $dataAccessEntry = array(
                        'userId'    => $User->getId(),
                        'dataId'    => $passwordId,
                        'dataKey'   => $encryptedPayloadKeyPart,
                        'keyPairId' => $UserAuthKeyPair->getId(),
                        'groupId'   => $groupId ?: null,
                    );

                    $dataAccessEntry['MAC'] = MAC::create(
                        implode('', $dataAccessEntry),
                        Utils::getSystemKeyPairAuthKey()
                    );

                    $DB->insert(
                        Tables::USER_TO_PASSWORDS,
                        $dataAccessEntry
                    );
                } catch (\Exception $Exception) {
                    // on error delete password entry
                    $DB->delete(
                        Tables::PASSWORDS,
                        array(
                            'id' => $passwordId
                        )
                    );

                    QUI\System\Log::addError(
                        'Error writing password key parts to database: ' . $Exception->getMessage()
                    );

                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.password.create.general.error'
                    ));
                }
            }
        }

        return $passwordId;
    }

    /**
     * @todo ggf. auch für nicht Session-User
     *
     * Get password object
     *
     * @param integer $id - password id
     * @return Password
     */
    public static function get($id)
    {
        if (isset(self::$passwords[$id])) {
            return self::$passwords[$id];
        }

        self::$passwords[$id] = new Password($id);

        return self::$passwords[$id];
    }

    /**
     * Get security class of password object
     *
     * @param integer $passwordId - Password ID
     * @return SecurityClass
     * @throws QUI\Exception
     */
    public static function getSecurityClass($passwordId)
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'securityClassId'
            ),
            'from' => Tables::PASSWORDS,
            'where' => array(
                'id' => $passwordId
            )
        ));

        if (empty($result)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.not.found', array(
                    'id' => $passwordId
                )
            ), 404);
        }

        $data = current($result);

        return Authentication::getSecurityClass($data['securityClassId']);
    }
}