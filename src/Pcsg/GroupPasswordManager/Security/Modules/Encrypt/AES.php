<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\Encrypt;

use Pcsg\GroupPasswordManager\Security\Interfaces\EncryptWrapper;
use

/**
 * This class provides a hashing API for the pcsg/grouppasswordmanager module
 */
class AES implements EncryptWrapper
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