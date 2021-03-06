<?php

namespace Sequry\Core\Security;

use Sequry\Core\Security\Interfaces\IHash;
use Sequry\Core\Security\HiddenString;

/**
 * This class provides a key derivation API for the sequry/core module
 */
class Hash
{
    const HASH_MODULE = 'Argon2'; // @todo in config auslagern

    /**
     * Hash Class Object for the configured hash module
     *
     * @var IHash
     */
    protected static $HashModule = null;
    
    /**
     * Creates a key from a given low entropy string
     *
     * @param HiddenString $str - A String
     * @param string $salt (optional) - if ommitted generate random salt
     * @return string - hash
     */
    public static function create(HiddenString $str, $salt = null)
    {
        return self::getHashModule()->create($str, $salt);
    }

    /**
     * @return IHash
     */
    protected static function getHashModule()
    {
        if (!is_null(self::$HashModule)) {
            return self::$HashModule;
        }

        $moduleClass = '\Sequry\Core\Security\Modules\Hash\\';
        $moduleClass .= self::HASH_MODULE;

        if (!class_exists($moduleClass)) {
            // @todo throw exception
            return false;
        }

        self::$HashModule = new $moduleClass();

        return self::$HashModule;
    }
}
