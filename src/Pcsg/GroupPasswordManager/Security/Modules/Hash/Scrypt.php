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
     */
    public static function createHash($str, $salt = null)
    {
        // @todo exceptions einbauen

        return ScryptClass::hash($str, $salt);
    }

    /**
     * Compares two hashes
     *
     * @param $expected
     * @param $actual
     * @return Bool - true if equal; false if not equal
     */
    public static function compareHash($expected, $actual)
    {
        return ScryptClass::compareStrings($expected, $actual);
    }
}