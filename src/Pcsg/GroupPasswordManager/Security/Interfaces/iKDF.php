<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

/**
 * This class provides a KDF API for the pcsg/grouppasswordmanager module
 */
interface iKDF
{
    /**
     * Creates a symmetric key with a key derivation function (KDF)
     *
     * @param string $str - A String
     * @param string $salt (optional) - if omitted genereate random salt
     * @return string - The MAC hash
     */
    public static function createKey($str, $salt = null);
}