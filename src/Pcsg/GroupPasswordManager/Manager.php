<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Password
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Security\Hash;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use QUI;

/**
 * Manager Class for passwords and crypto users
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Manager
{
    const TBL_PASSWORDS = 'pcsg_gpm_passwords';
    const TBL_USER_PASSWORDS = 'pcsg_gpm_user_passwords';
    const TBL_USERS = 'pcsg_gpm_users';

    /**
     * Create a new encrypted password entry
     *
     * @param String $title - Password title
     * @param String $description - Short description for the password
     * @param Array|String $payload - Payload (password data)
     * @param QUI\Users\User $User (optional) - Owner of the password [default: Session User]
     * @return Password
     * @throws QUI\Exception
     */
    public static function createPassword($title, $description, $payload, $User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        $title = trim($title);
        $description = trim($description);

        if (empty($title)
            || empty($description)) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.manager.create.password.missing.title.description'
                )
            );
        }

        // encrypt data
        $password = array(
            'payload' => $payload,
            'ownerId' => $User->getId()
        );

        $password = json_encode($password);
        $passwordKey = Hash::create($password);
        $passwordMAC = MAC::create($password, $passwordKey);

        \QUI\System\Log::writeRecursive( "---- start" );
        \QUI\System\Log::writeRecursive( $password );
        \QUI\System\Log::writeRecursive( $passwordKey );

        $cipherText = SymmetricCrypto::encrypt($password, $passwordKey);

        \QUI\System\Log::writeRecursive( "---- end" );

        QUI::getDataBase()->insert(
            self::TBL_PASSWORDS,
            array(
                'title' => $title,
                'description' => $description,
                'passwordData' => $cipherText,
                'passwordMac' => $passwordMAC
            )
        );

        $Password = new Password(
            QUI::getDataBase()->getPDO()->lastInsertId(),
            $passwordKey
        );

        // add basic view right for owner
        $Password->addViewUser(new CryptoUser($User->getId()));

        return $Password;
    }

//    /**
//     * @param $passwordId
//     * @param $passwordKey
//     * @return String
//     */
//    public static function getPassword($passwordId, $passwordKey)
//    {
//        return new Password($passwordId, $passwordKey);
//    }
//
//    public static function getCryptoUser($id)
//    {
//
//    }
}