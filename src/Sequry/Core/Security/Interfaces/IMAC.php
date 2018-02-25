<?php

namespace Sequry\Core\Security\Interfaces;

use Sequry\Core\Security\Keys\Key;
use Sequry\Core\Security\HiddenString;

/**
 * This class provides a MAC API for the sequry/core module
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
