<?php

namespace Pcsg\GroupPasswordManager\Security;

use Pcsg\GroupPasswordManager\Security\Interfaces\IMAC;
use Pcsg\GroupPasswordManager\Security\HiddenString;
use Pcsg\GroupPasswordManager\Security\Keys\Key;

/**
 * This class provides a MAC API for the pcsg/grouppasswordmanager module
 */
class MAC
{
    const MAC_MODULE = 'HMAC'; // @todo in config auslagern

    /**
     * HashWrapper Class Object for the configured hash module
     *
     * @var IMAC
     */
    protected static $MACModule = null;

    /**
     * Creates a MAC (Message Authentication Code)
     *
     * @param HiddenString $str - A String
     * @param Key $Key - Cryptographic Key for MAC generation
     * @return String - The MAC hash
     */
    public static function create(HiddenString $str, Key $Key)
    {
        $mac = self::getMACModule()->create($str, $Key);
        return $mac . Utils::getCryptoModuleVersionString(self::MAC_MODULE);
    }

    /**
     * Compare to MAC values (timing-safe)
     *
     * @param string $actual
     * @param string $expected
     * @return bool
     */
    public static function compare($actual, $expected)
    {
        $actual   = Utils::stripModuleVersionString($actual);
        $expected = Utils::stripModuleVersionString($expected);

        return self::getMACModule()->compare($actual, $expected);
    }

    /**
     * @return IMAC
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
