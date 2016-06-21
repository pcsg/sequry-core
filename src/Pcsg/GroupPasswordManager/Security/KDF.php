<?php

namespace Pcsg\GroupPasswordManager\Security;

use Pcsg\GroupPasswordManager\Security\Interfaces\HashWrapper;
use Pcsg\GroupPasswordManager\Security\Interfaces\iKDF;
use Pcsg\GroupPasswordManager\Security\Keys\Key;

/**
 * This class provides a key derivation API for the pcsg/grouppasswordmanager module
 */
class KDF
{
    const KDF_MODULE = 'Scrypt'; // @todo in config auslagern

    /**
     * Salt length [bits]
     *
     * @var Integer
     */
    const SALT_LENGTH = 64;

    /**
     * KDF Class Object for the configured hash module
     *
     * @var iKDF
     */
    protected static $KDFModule = null;
    
    /**
     * Creates a key from a given low entropy string
     *
     * @param string $str - A String
     * @param string $salt (optional) - if ommitted generate random salt
     * @return Key - symmetric key
     */
    public static function createKey($str, $salt = null)
    {
        $keyValue = self::getKDFModule()->create($str, $salt);
        return new Key($keyValue);
    }

    /**
     * @return HashWrapper
     */
    protected static function getKDFModule()
    {
        if (!is_null(self::$KDFModule)) {
            return self::$KDFModule;
        }

        $moduleClass = '\Pcsg\GroupPasswordManager\Security\Modules\KDF\\';
        $moduleClass .= self::KDF_MODULE;

        if (!class_exists($moduleClass)) {
            // @todo throw exception
            return false;
        }

        self::$KDFModule = new $moduleClass();

        return self::$KDFModule;
    }
}