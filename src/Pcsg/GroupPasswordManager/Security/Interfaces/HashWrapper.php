<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

/**
 * This class provides a hashing API for the pcsg/grouppasswordmanager module
 */
interface HashWrapper
{
    /**
     * Creates a hash value from a given string
     *
     * @param String $str - A String
     * @param String $salt (optional)
     */
    public static function createHash($str, $salt = null);

    /**
     * Compares two hashes
     *
     * @param $expected
     * @param $actual
     * @return Bool - true if equal; false if not equal
     */
    public static function compareHash($expected, $actual);
}