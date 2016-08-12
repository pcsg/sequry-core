<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\CSPRNG;

use Pcsg\GroupPasswordManager\Security\Interfaces\ICSPRNG;
use QUI;

/**
 * This class provides a hashing API for the pcsg/grouppasswordmanager module
 */
class Libsodium implements ICSPRNG
{
    /**
     * Creates random data
     *
     * @param integer $length - length [bits]
     * @return string - randomly generated data
     */
    public static function getRandomData($length)
    {
        $length /= 8; // convert bits to bytes
        return \Sodium\randombytes_buf($length);
    }
}
