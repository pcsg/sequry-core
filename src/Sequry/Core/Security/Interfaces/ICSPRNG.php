<?php

namespace Sequry\Core\Security\Interfaces;

/**
 * This class provides a Cryptographically Secure Pseudo Random Number Generator (CSPRNG)
 * API for the sequry/core module
 */
interface ICSPRNG
{
    /**
     * Creates random data
     *
     * @param integer $length - length [bits]
     * @return string - randomly generated data
     */
    public static function getRandomData($length);
}
