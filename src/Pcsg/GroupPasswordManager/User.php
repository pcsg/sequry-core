<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\User
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Security\Password;
use QUI;

/**
 * User Class
 *
 * Represents a password manager User that can retrieve encrypted passwords
 * if it has the necessary rights.
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class User extends QUI\Users\User
{
    public function generateKeyPair()
    {

    }

    public function getPrivateKey()
    {

    }

    public static function test()
    {
        $hash = Password::hash(
            'pferd',
            Password::generateSalt()
        );

        \QUI\System\Log::writeRecursive( $hash );
    }
}