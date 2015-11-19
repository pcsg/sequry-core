<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

/**
 * This class provides a asymmetric encryption/decryption API for the pcsg/grouppasswordmanager module
 */
interface AsymmetricCryptoWrapper
{
    /**
     * Encrypts a plaintext string
     *
     * @param String $plainText - Data to be encrypted
     * @param String $publicKey - Public encryption key
     * @return String - The Ciphertext (encrypted plaintext)
     */
    public static function encrypt($plainText, $publicKey);

    /**
     * Decrypts a ciphertext
     *
     * @param String $cipherText - Data to be decrypted
     * @param String $privateKey - Private decryption key
     * @param String $password (optional) - Password for private key
     * @return String - The plaintext (decrypted ciphertext)
     */
    public static function decrypt($cipherText, $privateKey);


    /**
     * Generates a new public/private key pair
     *
     * @return Array - "privateKey" and "publicKey"
     */
    public static function generateKeyPair();
}