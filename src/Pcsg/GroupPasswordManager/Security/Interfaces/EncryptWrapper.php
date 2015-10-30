<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

/**
 * This class provides a encryption/decryption API for the pcsg/grouppasswordmanager module
 */
interface EncryptWrapper
{
    /**
     * Creates a hash value from a given string
     *
     * @param String $plainText - Data to be encrypted
     * @param String $key - Encryption key
     */
    public static function encrypt($plainText, $key);

    /**
     * Compares two hashes
     *
     * @param String $cipherText - Data to be decrypted
     * @param String $key - Decryption key
     */
    public static function decrypt($cipherText, $key);
}