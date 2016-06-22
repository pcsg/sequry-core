<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Security\Handler\Passwords
 */

namespace Pcsg\GroupPasswordManager\Security\Handler;

use Pcsg\GroupPasswordManager\CryptoUser;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
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

        switch ($owner['type']) {
            case 'user':
                try {
                    $OwnerUser = QUI::getUsers()->get($owner['id']);
                } catch (QUI\Exception $Exception) {
                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.passwords.create.owner.incorrect.data'
                    ));
                }
                break;

            case 'group':
                // @todo
                break;

            default:
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.passwords.create.owner.incorrect.data'
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

        $payload    = $passwordData['payload'];
        $payloadKey = SymmetricCrypto::generateKey();

        // split key


        // session user
        $CryptoUser = CryptoActors::getCryptoUser();


    }
}