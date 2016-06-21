<?php

namespace Pcsg\GroupPasswordManager\Security;

use Pcsg\GroupPasswordManager\Security\Interfaces\HashWrapper;
use Pcsg\GroupPasswordManager\Security\Interfaces\iKDF;
use Pcsg\GroupPasswordManager\Security\Keys\Key;

/**
 * This class provides a key derivation API for the pcsg/grouppasswordmanager module
 */
class Random
{
    const CSPRNG_MODULE = 'Scrypt'; // @todo in config auslagern

    /**
     * Random data length length [bits]
     *
     * @var Integer
     */
    const RANDOM_DATA_LENGTH = 256;

    /**
     * Random Number Generator Class Object for the configured hash module
     *
     * @var iKDF
     */
    protected static $RNGModule = null;

    /**
     * Creates random data
     *
     * @param integer $length (optional) - length [bits]; if ommitted use reasonable value
     * @return string - randomly generated data
     */
    public static function getRandomData($length = null)
    {

    }

    /**
     * @return HashWrapper
     */
    protected static function getRNGModule()
    {
        if (!is_null(self::$RNGModule)) {
            return self::$RNGModule;
        }

        $moduleClass = '\Pcsg\GroupPasswordManager\Security\Modules\CSPRNG\\';
        $moduleClass .= self::CSPRNG_MODULE;

        if (!class_exists($moduleClass)) {
            // @todo throw exception
            return false;
        }

        self::$RNGModule = new $moduleClass();

        return self::$RNGModule;
    }
}