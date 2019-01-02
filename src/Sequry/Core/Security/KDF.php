<?php

namespace Sequry\Core\Security;

use Sequry\Core\Security\Interfaces\IKDF;
use Sequry\Core\Security\Keys\Key;

/**
 * This class provides a key derivation API for the sequry/core module
 */
class KDF
{
    const KDF_MODULE = 'Halite4'; // @todo in config auslagern

    /**
     * KDF Class Object for the configured hash module
     *
     * @var IKDF
     */
    protected static $KDFModule = null;
    
    /**
     * Creates a key from a given low entropy string
     *
     * @param HiddenString $str - A String
     * @param string $salt (optional) - if ommitted generate random salt
     * @return Key - symmetric key
     */
    public static function createKey(HiddenString $str, $salt = null)
    {
        return self::getKDFModule()->createKey($str, $salt);
    }

    /**
     * @return IKDF
     */
    protected static function getKDFModule()
    {
        if (!is_null(self::$KDFModule)) {
            return self::$KDFModule;
        }

        $moduleClass = '\Sequry\Core\Security\Modules\KDF\\';
        $moduleClass .= self::KDF_MODULE;

        if (!class_exists($moduleClass)) {
            // @todo throw exception
            return false;
        }

        self::$KDFModule = new $moduleClass();

        return self::$KDFModule;
    }
}
