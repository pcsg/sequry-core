<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

/**
 * This class provides a hashing/KDF API for the pcsg/grouppasswordmanager module
 */
interface HashWrapper
{
    /**
     * Creates a hash value from a given string
     *
     * @param String $str - A String
     * @param String $salt (optional)
     */
    public static function create($str, $salt = null);
}