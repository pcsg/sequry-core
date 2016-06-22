<?php

namespace Pcsg\GroupPasswordManager\Security;

use Pcsg\GroupPasswordManager\Security\Interfaces\iMAC;

/**
 * This class provides a MAC API for the pcsg/grouppasswordmanager module
 */
class MAC
{
    const MAC_MODULE = 'HMAC'; // @todo in config auslagern

    /**
     * HashWrapper Class Object for the configured hash module
     *
     * @var iMAC
     */
    protected static $MACModule = null;

    /**
     * Creates a MAC (Message Authentication Code)
     *
     * @param String $str - A String
     * @param String $key - Private key for MAC generation
     * @return String - The MAC hash
     */
    public static function create($str, $key)
    {
        return self::getMACModule()->create($str, $key);
    }

    /**
     * @return iMAC
     */
    protected static function getMACModule()
    {
        if (!is_null(self::$MACModule)) {
            return self::$MACModule;
        }

        $moduleClass = '\Pcsg\GroupPasswordManager\Security\Modules\MAC\\';
        $moduleClass .= self::MAC_MODULE;

        if (!class_exists($moduleClass)) {
            // @todo throw exception
            return false;
        }

        self::$MACModule = new $moduleClass();

        return self::$MACModule;
    }
}