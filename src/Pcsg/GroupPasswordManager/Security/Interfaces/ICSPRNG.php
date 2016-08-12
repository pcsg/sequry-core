<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

/**
 * This class provides a Cryptographically Secure Pseudo Random Number Generator (CSPRNG)
 * API for the pcsg/grouppasswordmanager module
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
