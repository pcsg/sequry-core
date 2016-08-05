<?php

namespace Pcsg\GroupPasswordManager\Security;

use Pcsg\GroupPasswordManager\Security\Interfaces\iHash;
use Pcsg\GroupPasswordManager\Security\Interfaces\iKDF;
use Pcsg\GroupPasswordManager\Security\Keys\Key;

/**
 * This class provides a key derivation API for the pcsg/grouppasswordmanager module
 */
class Hash
{
    const HASH_MODULE = 'Scrypt'; // @todo in config auslagern

    /**
     * Hash Class Object for the configured hash module
     *
     * @var iHash
     */
    protected static $HashModule = null;
    
    /**
     * Creates a key from a given low entropy string
     *
     * @param string $str - A String
     * @param string $salt (optional) - if ommitted generate random salt
     * @return Key - symmetric key
     */
    public static function create($str, $salt = null)
    {
        return self::getHashModule()->create($str, $salt);
    }

    /**
     * @return iHash
     */
    protected static function getHashModule()
    {
        if (!is_null(self::$HashModule)) {
            return self::$HashModule;
        }

        $moduleClass = '\Pcsg\GroupPasswordManager\Security\Modules\Hash\\';
        $moduleClass .= self::HASH_MODULE;

        if (!class_exists($moduleClass)) {
            // @todo throw exception
            return false;
        }

        self::$HashModule = new $moduleClass();

        return self::$HashModule;
    }
}