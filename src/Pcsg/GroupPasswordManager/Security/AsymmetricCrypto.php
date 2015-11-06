<?php

namespace Pcsg\GroupPasswordManager\Security;

use Pcsg\GroupPasswordManager\Security\Interfaces\AsymmetricCryptoWrapper;

/**
 * This class provides a symmetric encryption API for the pcsg/grouppasswordmanager module
 */
class AsymmetricCrypto
{
    const CRYPTO_MODULE = 'RSA'; // @todo in config auslagern

    /**
     * HashWrapper Class Object for the configured hash module
     *
     * @var AsymmetricCryptoWrapper
     */
    protected static $_CryptoModule = null;

    /**
     * Encrypts a plaintext string
     *
     * @param String $plainText - Data to be encrypted
     * @param String $publicKey - Public encryption key
     * @return String - The Ciphertext (encrypted plaintext)
     */
    public static function encrypt($plainText, $publicKey)
    {
        return self::_getCryptoModule()->encrypt($plainText, $publicKey);
    }

    /**
     * Decrypts a ciphertext
     *
     * @param String $cipherText - Data to be decrypted
     * @param String $privateKey - Private decryption key
     * @param String $password (optional) - Password for private key
     * @return String - The plaintext (decrypted ciphertext)
     */
    public static function decrypt($cipherText, $privateKey, $password = null)
    {
        return self::_getCryptoModule()->decrypt(
            $cipherText,
            $privateKey,
            $password
        );
    }

    /**
     * Generates a new public/private key pair
     *
     * @param String $password (optional) - Password to protect the private key
     * @return Array - "privateKey" and "publicKey"
     */
    public static function generateKeyPair($password = null)
    {
        return self::_getCryptoModule()->generateKeyPair($password);
    }

    /**
     * Get Crypto Module for symmetric encryption/decryption
     *
     * @return AsymmetricCryptoWrapper
     */
    protected static function _getCryptoModule()
    {
        if (!is_null(self::$_CryptoModule)) {
            return self::$_CryptoModule;
        }

        $moduleClass = '\Pcsg\GroupPasswordManager\Security\Modules\AsymmetricCrypto\\';
        $moduleClass .= self::CRYPTO_MODULE;

        if (!class_exists($moduleClass)) {
            // @todo throw exception
            return false;
        }

        self::$_CryptoModule = new $moduleClass();

        return self::$_CryptoModule;
    }
}