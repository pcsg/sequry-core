<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

/**
 * This class provides a encryption/decryption API for the pcsg/grouppasswordmanager module
 */
interface SymmetricCryptoWrapper
{
    /**
     * Encrypts a plaintext string
     *
     * @param String $plainText - Data to be encrypted
     * @param String $key - Encryption key
     * @return String - The Ciphertext (encrypted plaintext)
     */
    public static function encrypt($plainText, $key);

    /**
     * Decrypts a ciphertext
     *
     * @param String $cipherText - Data to be decrypted
     * @param String $key - Decryption key
     * @return String - The plaintext (decrypted ciphertext)
     */
    public static function decrypt($cipherText, $key);

    /**
     * Generate a new, random symmetric key
     *
     * @return String - The random key
     */
    public static function generateKey();
}