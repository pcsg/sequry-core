<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\MAC;

use Pcsg\GroupPasswordManager\Security\Interfaces\iMAC;
use phpseclib\Crypt\Hash as HMACClass;

/**
 * This class provides a hashing API for the pcsg/grouppasswordmanager module
 */
class HMAC implements iMAC
{
    const HASH_ALGO = 'sha256';

    /**
     * Class for HMAC generation
     *
     * @var HMACClass
     */
    protected static $HMACClass = null;

    /**
     * Creates a MAC (Message Authentication Code)
     *
     * @param String $str - A String
     * @param String $key - Private key for MAC generation
     * @return String - The MAC hash
     */
    public static function create($str, $key)
    {
        $HMACClass = self::getHMACClass();
        $HMACClass->setKey($key);
        return $HMACClass->hash($str);
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
        // >=PHP5.6 only
        if (function_exists('hash_equals')) {
            return hash_equals($expected, $actual);
        }

        $expected    = (string) $expected;
        $actual      = (string) $actual;
        $lenExpected = mb_strlen($expected);
        $lenActual   = mb_strlen($actual);
        $len         = min($lenExpected, $lenActual);

        $result = 0;

        for ($i = 0; $i < $len; $i ++) {
            $result |= ord($expected[$i]) ^ ord($actual[$i]);
        }

        $result |= $lenExpected ^ $lenActual;

        return $result === 0;
    }

    /**
     * Returns an instance of the phpseclib/Hash Class
     *
     * @return HMACClass
     */
    protected static function getHMACClass()
    {
        if (!is_null(self::$HMACClass)) {
            return self::$HMACClass;
        }

        self::$HMACClass = new HMACClass(self::HASH_ALGO);

        return self::$HMACClass;
    }
}