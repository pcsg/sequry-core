<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

/**
 * This class provides a encryption/decryption API for the pcsg/grouppasswordmanager module
 */
interface ISymmetricCrypto
{
    /**
     * Encrypts a plaintext string
     *
     * @param string $plainText - Data to be encrypted
     * @param string $key - Encryption key
     * @return string - The Ciphertext (encrypted plaintext)
     */
    public static function encrypt($plainText, $key);

    /**
     * Decrypts a ciphertext
     *
     * @param string $cipherText - Data to be decrypted
     * @param string $key - Decryption key
     * @return string - The plaintext (decrypted ciphertext)
     */
    public static function decrypt($cipherText, $key);

    /**
     * Generate a new, random symmetric key
     *
     * @return string - The random key
     */
    public static function generateKey();
}
