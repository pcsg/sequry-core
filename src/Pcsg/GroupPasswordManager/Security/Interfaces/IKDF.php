<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

use Pcsg\GroupPasswordManager\Security\HiddenString;
use Pcsg\GroupPasswordManager\Security\Keys\Key;

/**
 * This class provides a KDF API for the pcsg/grouppasswordmanager module
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
