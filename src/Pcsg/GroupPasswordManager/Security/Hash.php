<?php

namespace Pcsg\GroupPasswordManager\Security;

use Pcsg\GroupPasswordManager\Security\Interfaces\HashWrapper;

/**
 * This class provides a hashing API for the pcsg/grouppasswordmanager module
 */
class Hash
{
    const HASH_MODULE = 'Scrypt'; // @todo in config auslagern

    /**
     * HashWrapper Class Object for the configured hash module
     *
     * @var HashWrapper
     */
    protected static $_HashModule = null;
    
    /**
     * Creates a hash value from a given string
     *
     * @param String $str - A String
     * @param String $salt (optional)
     */
    public static function create($str, $salt = null)
    {
        return self::_getHashModule()->create($str, $salt);
    }

    /**
     * @return HashWrapper
     */
    protected static function _getHashModule()
    {
        if (!is_null(self::$_HashModule)) {
            return self::$_HashModule;
        }

        $moduleClass = '\Pcsg\GroupPasswordManager\Security\Modules\Hash\\';
        $moduleClass .= self::HASH_MODULE;

        if (!class_exists($moduleClass)) {
            // @todo throw exception
            return false;
        }

        self::$_HashModule = new $moduleClass();

        return self::$_HashModule;
    }
}