<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

use Pcsg\GroupPasswordManager\Security\HiddenString;
use Pcsg\GroupPasswordManager\Security\Keys\Key;

/**
 * This class provides a encryption/decryption API for the pcsg/grouppasswordmanager module
 */
interface ISymmetricCrypto
{
    /**
     * Encrypts a plaintext string
     *
     * @param HiddenString $plainText - Data to be encrypted
     * @param Key $Key - Encryption key
     * @return string - The Ciphertext (encrypted plaintext)
     */
    public static function encrypt(HiddenString $plainText, Key $Key);

    /**
     * Decrypts a ciphertext
     *
     * @param string $cipherText - Data to be decrypted
     * @param Key $Key - Decryption key
     * @return HiddenString - The plaintext (decrypted ciphertext)
     */
    public static function decrypt($cipherText, Key $Key);

    /**
     * Generate a new, random symmetric key
     *
     * @return string - The random key
     */
    public static function generateKey();
}
