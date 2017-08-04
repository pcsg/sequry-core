<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

use Pcsg\GroupPasswordManager\Security\Keys\Key;
use Pcsg\GroupPasswordManager\Security\HiddenString;

/**
 * This class provides a MAC API for the pcsg/grouppasswordmanager module
 */
interface IMAC
{
    /**
     * Creates a MAC (Message Authentication Code)
     *
     * @param HiddenString $str - A String
     * @param Key $Key - Cryptographic Key for MAC generation
     * @return string - The MAC
     */
    public static function create(HiddenString $str, Key $Key);

    /**
     * Compare to MAC values (timing-safe)
     *
     * @param string $actual
     * @param string $expected
     * @return bool
     */
    public static function compare($actual, $expected);
}
