<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\MAC;

use Pcsg\GroupPasswordManager\Security\Interfaces\MACWrapper;
use phpseclib\Crypt\Hash as HMACClass;

/**
 * This class provides a hashing API for the pcsg/grouppasswordmanager module
 */
class HMAC implements MACWrapper
{
    const HASH_ALGO = 'sha256';

    /**
     * Class for HMAC generation
     *
     * @var HMACClass
     */
    protected static $_HMACClass = null;

    /**
     * Creates a MAC (Message Authentication Code)
     *
     * @param String $str - A String
     * @param String $key - Private key for MAC generation
     * @return String - The MAC hash
     */
    public static function create($str, $key)
    {
        $HMACClass = self::_getHMACClass();
        $HMACClass->setKey($key);
        return $HMACClass->hash($str);
    }

    /**
     * Returns an instance of the phpseclib/Hash Class
     *
     * @return HMACClass
     */
    protected static function _getHMACClass()
    {
        if (!is_null(self::$_HMACClass)) {
            return self::$_HMACClass;
        }

        self::$_HMACClass = new HMACClass(self::HASH_ALGO);

        return self::$_HMACClass;
    }
}