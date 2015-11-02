<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Password
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Security\Encrypt;
use Pcsg\GroupPasswordManager\Security\Hash;
use QUI;

/**
 * Password Class
 *
 * Represents a secret passphrase and/or login information that is stored
 * encrypted.
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class PasswordManager
{
    const TBL_PASSWORDS = 'pcsg_gpm_password';
    const TBL_USER_PASSWORDS = 'pcsg_gpm_user_passwords';

    /**
     * constructor
     *
     * @param String $title - Password title
     * @param String $description - Short description for the password
     * @param Array|String - Data for the password
     * @return Array - Information about newly created password data
     */
    public static function create($title, $description, $data)
    {
        // encrypt data
        $hash = hash('sha256', json_encode($data));
        $password = array(
            'data' => $data,
            'hash' => $hash
        );

        $password = json_encode($password);
        $passwordKey = Hash::createHash($password);

        $cipherText = Encrypt::encrypt($password, $passwordKey);

        QUI::getDataBase()->insert(
            'pcsg_gpm_passwords',
            array(
                'title' => $title,
                'description' => $description,
                'password_data' => $cipherText
            )
        );

        $newPass = array(
            'id' => QUI::getDataBase()->getPDO()->lastInsertId(),
            'key' => $passwordKey
        );

        return $newPass;
    }

    public static function get($passwordId, $passwordKey)
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'title',
                'description',
                'password_data'
            ),
            'from' => 'pcsg_gpm_passwords',
            'where' => array(
                'id' => $passwordId
            )
        ));

        // @todo return correct data
        return Encrypt::decrypt($result[0]['password_data'], $passwordKey);
    }
}