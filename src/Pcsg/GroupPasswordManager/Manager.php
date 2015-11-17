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
    // Password tables
    const TBL_PASSWORDS = 'pcsg_gpm_passwords';

    // User tables
    const TBL_USERS = 'pcsg_gpm_users';
    const TBL_USER_PASSWORDS = 'pcsg_gpm_user_passwords';
    const TBL_USER_GROUPS = 'pcsg_gpm_user_groups';

    // Group tables
    const TBL_GROUPS = 'pcsg_gpm_groups';
    const TBL_GROUP_PASSWORDS = 'pcsg_gpm_group_passwords';

    /**
     * Create a new encrypted password entry
     *
     * @param String $title - Password title
     * @param String $description - Short description for the password
     * @param Array|String $payload - Payload (password data)
     * @param QUI\Users\User $Owner (optional) - Owner of the password [default: Session User]
     * @return Password
     * @throws QUI\Exception
     */
    public static function createPassword(
        $title,
        $description,
        $payload,
        $Owner = null
    ) {
        if (is_null($Owner)) {
            $Owner = QUI::getUserBySession();
        }

        $title       = trim($title);
        $description = trim($description);

        if (empty($title)
            || empty($description)
        ) {
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
            'ownerId' => $Owner->getId()
        );

        $password    = json_encode($password);
        $passwordKey = Hash::create($password);
        $passwordMAC = MAC::create($password, $passwordKey);

        $cipherText = SymmetricCrypto::encrypt($password, $passwordKey);

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
        $Password->addViewUser(new CryptoUser($Owner->getId()));

        return $Password;
    }

    /**
     * @param QUI\Groups\Group $Group - The QUIQQER Group
     * @param QUI\Users\User $Owner (optional) - Owner of the group [default: Session User]
     * @return CryptoGroup
     */
    public static function createCryptoGroup($Group, $Owner = null)
    {

    }

    /**
     * Creates a CryptoUser with public/private key pair related to a QUIQQER User
     *
     * @param QUI\Users\User $User - Related QUIQQER User
     * @return CryptoUser
     */
    public static function createCryptoUser(QUI\Users\User $User)
    {
        $CrpyotUser = new CryptoUser($User->getId());
        $CrpyotUser->generateKeyPair();

        return $CrpyotUser;
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