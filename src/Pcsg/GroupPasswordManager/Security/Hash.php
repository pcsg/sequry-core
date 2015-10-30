<?php

namespace Pcsg\GroupPasswordManager\Security;

/**
 * This class provides a hashing API for the pcsg/grouppasswordmanager module
 */
class Hash
{
    const HASH_MODULE = 'Scrypt'; // @todo in config auslagern

    /**
     * Creates a hash value from a given string
     *
     * @param String $str - A String
     * @param String $salt (optional)
     */
    public static function createHash($str, $salt = null)
    {
        return self::_getHashWrapper()->createHash($str, $salt);
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
        return self::_getHashWrapper()->compareHash($expected, $actual);
    }

    protected static function _getHashWrapper()
    {
        $moduleClass = '\Pcsg\GroupPasswordManager\Security\Modules\Hash\\';
        $moduleClass .= self::HASH_MODULE;

        if (!class_exists($moduleClass)) {
            // @todo throw exception
            return false;
        }

        return new $moduleClass();
    }
}