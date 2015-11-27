<?php

namespace Pcsg\GroupPasswordManager\Security;

/**
 * This class provides general security function the pcsg/grouppasswordmanager module
 */
class Utils
{
    /**
     * Zend Framework (http://framework.zend.com/)
     *
     * @link      http://github.com/zendframework/zf2 for the canonical source repository
     * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
     * @license   http://framework.zend.com/license/new-bsd New BSD License
     *
     * Compare two strings to avoid timing attacks
     *
     * C function memcmp() internally used by PHP, exits as soon as a difference
     * is found in the two buffers. That makes possible of leaking
     * timing information useful to an attacker attempting to iteratively guess
     * the unknown string (e.g. password).
     *
     * @param string $expected
     * @param string $actual
     *
     * @return boolean If the two strings match.
     */
    public static function compareStrings($expected, $actual)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($expected, $actual);
        }

        $expected    = (string) $expected;
        $actual      = (string) $actual;
        $lenExpected = mb_strlen($expected);
        $lenActual   = mb_strlen($actual);
        $len         = min($lenExpected, $lenActual);

        $result = 0;

        for ($i = 0; $i < $len; $i ++) {
            $result |= ord($expected[$i]) ^ ord($actual[$i]);
        }

        $result |= $lenExpected ^ $lenActual;

        return $result === 0;
    }

    /**
     * Split a key into multiple parts that can be XOR'ed together to get
     * the original key
     *
     * @param String $key
     * @param Integer $parts - Number of parts the key shall be split into
     * @return Array - Key parts
     */
    public static function splitKey($key, $parts)
    {
        // get byte length of key
        $keyBytes = mb_strlen($key, '8bit');
        $parts = (int)$parts;

        if ($parts < 2) {
            return $key;
        }

        $splitKeys = array();
        $value = $key;

        for ($i = 1; $i < $parts; $i++) {
            // generate random bytes in key size
            $rndBytes = \Sodium\randombytes_buf($keyBytes);
            $newPart = $value ^ $rndBytes;

            $splitKeys[] = $rndBytes;
            $value = $newPart;
        }

        $splitKeys[] = $newPart;

        return $splitKeys;
    }

    /**
     * Join parts of a key to retrieve the original key
     *
     * @param Array $parts
     * @return String
     */
    public static function joinKeyParts($parts)
    {
        if (count($parts) < 2) {
            return current($parts);
        }

        // start with appropiate length of 0-bytes (assumes all parts are of the same byte-length)
        $key = \str_repeat("\x00", mb_strlen(current($parts), '8bit'));

        foreach ($parts as $part) {
            $key ^= $part;
        }

        return $key;
    }
}