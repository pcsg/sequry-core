<?php

namespace Sequry\Core\Security\Interfaces;

use Sequry\Core\Security\HiddenString;
use Sequry\Core\Security\Keys\Key;

/**
 * This class provides a KDF API for the sequry/core module
 */
interface IKDF
{
    /**
     * Creates a symmetric key with a key derivation function (KDF)
     *
     * @param HiddenString $str - A String
     * @param string $salt (optional) - if omitted genereate random salt
     * @return Key
     */
    public static function createKey(HiddenString $str, $salt = null);
}
