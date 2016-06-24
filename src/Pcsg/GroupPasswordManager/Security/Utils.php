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
        // >=PHP5.6 only
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
     * Get system authentication key for key pairs
     *
     * @return string
     * @throws \QUI\Exception
     */
    public static function getSystemKeyPairAuthKey()
    {
        $keyFile = ETC_DIR . 'plugins/pcsg/gpm_auth_keypairs.key';

        if (!file_exists($keyFile)) {
            throw new \QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.system.auth.key.file.not.found'
            ), 404);
        }

        return file_get_contents($keyFile);
    }

    /**
     * Get system authentication key for passwords
     *
     * @return string
     * @throws \QUI\Exception
     */
    public static function getSystemPasswordAuthKey()
    {
        $keyFile = ETC_DIR . 'plugins/pcsg/gpm_auth_passwords.key';

        if (!file_exists($keyFile)) {
            throw new \QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.system.auth.key.file.not.found'
            ), 404);
        }

        return file_get_contents($keyFile);
    }
}