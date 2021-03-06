<?php

namespace Sequry\Core\Security\Interfaces;

use Sequry\Core\Security\HiddenString;
use Sequry\Core\Security\Keys\KeyPair;

/**
 * This class provides a asymmetric encryption/decryption API for the sequry/core module
 */
interface IAsymmetricCrypto
{
    /**
     * Encrypts a plaintext string
     *
     * @param HiddenString $plainText - Data to be encrypted
     * @param KeyPair $KeyPair - Encryption KeyPair
     * @return string - The Ciphertext (encrypted plaintext)
     */
    public static function encrypt(HiddenString $plainText, KeyPair $KeyPair);

    /**
     * Decrypts a ciphertext
     *
     * @param string $cipherText - Data to be decrypted
     * @param KeyPair $KeyPair - Decryption KeyPair
     * @return HiddenString - The plaintext (decrypted ciphertext)
     */
    public static function decrypt($cipherText, KeyPair $KeyPair);


    /**
     * Generates a new public/private key pair
     *
     * @return KeyPair
     */
    public static function generateKeyPair();
}
