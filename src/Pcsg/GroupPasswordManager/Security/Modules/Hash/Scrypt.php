<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\Hash;

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
     */
    public static function create($str, $salt = null)
    {
        return ScryptClass::createHash($str, $salt);
    }
}