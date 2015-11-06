<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Password
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Security\Hash;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use QUI;

/**
 * Manager Class for passwords and crypto users
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Manager
{
    const TBL_PASSWORDS = 'pcsg_gpm_password';
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
     */
    public static function createPassword($title, $description, $payload, $User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        // encrypt data
        $hash = hash('sha256', json_encode($payload));
        $password = array(
            'payload' => $payload,
            'hash' => $hash,
            'ownerId' => $User->getId(),
            'edit' => array() // contains all user ids of users that can edit this password
        );

        $password = json_encode($password);
        $passwordKey = Hash::create($password);

        $cipherText = SymmetricCrypto::encrypt($password, $passwordKey);

        QUI::getDataBase()->insert(
            'pcsg_gpm_passwords',
            array(
                'title' => $title,
                'description' => $description,
                'password_data' => $cipherText
            )
        );

        return new Password(
            QUI::getDataBase()->getPDO()->lastInsertId(),
            $passwordKey
        );
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