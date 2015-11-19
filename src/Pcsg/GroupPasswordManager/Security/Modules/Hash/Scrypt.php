<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\Hash;

use QUI;
use Pcsg\GroupPasswordManager\Security\Interfaces\HashWrapper;
use Pcsg\GroupPasswordManager\Security\Classes\Scrypt as ScryptClass;

/**
 * This class provides a hashing API for the pcsg/grouppasswordmanager module
 */
class Scrypt implements HashWrapper
{
    /**
     * Creates a hash value from a given string
     *
     * @param String $str - A String
     * @param String $salt (optional)
     * @return String - hashed string
     * @throws QUI\Exception
     */
    public static function create($str, $salt = null)
    {
        try {
            $hash = ScryptClass::createHash($str, $salt);
        } catch (QUI\Exception $Exception) {
            throw new QUI\Exception(
                'Scrypt :: Hash operation failed: ' . $Exception->getMessage()
            );
        }
        
        return $hash;
    }
}