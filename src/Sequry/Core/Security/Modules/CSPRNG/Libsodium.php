<?php

namespace Sequry\Core\Security\Modules\CSPRNG;

use Sequry\Core\Security\Interfaces\ICSPRNG;

/**
 * This class provides a hashing API for the sequry/core module
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
