<?php

namespace Pcsg\GroupPasswordManager\Security;

use Pcsg\GroupPasswordManager\Security\Interfaces\MACWrapper;

/**
 * This class provides a MAC API for the pcsg/grouppasswordmanager module
 */
class MAC
{
    const MAC_MODULE = 'HMAC'; // @todo in config auslagern

    /**
     * HashWrapper Class Object for the configured hash module
     *
     * @var MACWrapper
     */
    protected static $_MACModule = null;

    /**
     * Creates a MAC (Message Authentication Code)
     *
     * @param String $str - A String
     * @param String $key - Private key for MAC generation
     * @return String - The MAC hash
     */
    public static function create($str, $key)
    {
        return self::_getMACModule()->create($str, $key);
    }

    /**
     * @return MACWrapper
     */
    protected static function _getMACModule()
    {
        if (!is_null(self::$_MACModule)) {
            return self::$_MACModule;
        }

        $moduleClass = '\Pcsg\GroupPasswordManager\Security\Modules\MAC\\';
        $moduleClass .= self::MAC_MODULE;

        if (!class_exists($moduleClass)) {
            // @todo throw exception
            return false;
        }

        self::$_MACModule = new $moduleClass();

        return self::$_MACModule;
    }
}