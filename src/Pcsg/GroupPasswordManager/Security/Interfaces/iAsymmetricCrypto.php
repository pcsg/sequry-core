<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

/**
 * This class provides a asymmetric encryption/decryption API for the pcsg/grouppasswordmanager module
 */
interface iAsymmetricCrypto
{
    /**
     * Encrypts a plaintext string
     *
     * @param string $plainText - Data to be encrypted
     * @param string $publicKey - Public encryption key
     * @return string - The Ciphertext (encrypted plaintext)
     */
    public static function encrypt($plainText, $publicKey);

    /**
     * Decrypts a ciphertext
     *
     * @param string $cipherText - Data to be decrypted
     * @param string $privateKey - Private decryption key
     * @return string - The plaintext (decrypted ciphertext)
     */
    public static function decrypt($cipherText, $privateKey);


    /**
     * Generates a new public/private key pair
     *
     * @return array - "privateKey" and "publicKey"
     */
    public static function generateKeyPair();
}