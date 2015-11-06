<?php

namespace Pcsg\GroupPasswordManager\Security;

use Pcsg\GroupPasswordManager\Security\Interfaces\SymmetricCryptoWrapper;

/**
 * This class provides a symmetric encryption API for the pcsg/grouppasswordmanager module
 */
class SymmetricCrypto
{
    const CRYPTO_MODULE = 'AES'; // @todo in config auslagern

    /**
     * HashWrapper Class Object for the configured hash module
     *
     * @var SymmetricCryptoWrapper
     */
    protected static $_CryptoModule = null;

    /**
     * Encrypts a plaintext string
     *
     * @param String $plainText - Data to be encrypted
     * @param String $key - Encryption key
     * @return String - The Ciphertext (encrypted plaintext)
     */
    public static function encrypt($plainText, $key)
    {
        return self::_getCryptoModule()->encrypt($plainText, $key);
    }

    /**
     * Decrypts a ciphertext
     *
     * @param String $cipherText - Data to be decrypted
     * @param String $key - Decryption key
     * @return String - The plaintext (decrypted ciphertext)
     */
    public static function decrypt($cipherText, $key)
    {
        return self::_getCryptoModule()->decrypt($cipherText, $key);
    }

    /**
     * Get Crypto Module for symmetric encryption/decryption
     *
     * @return SymmetricCryptoWrapper
     */
    protected static function _getCryptoModule()
    {
        if (!is_null(self::$_CryptoModule)) {
            return self::$_CryptoModule;
        }

        $moduleClass = '\Pcsg\GroupPasswordManager\Security\Modules\SymmetricCrypto\\';
        $moduleClass .= self::CRYPTO_MODULE;

        if (!class_exists($moduleClass)) {
            // @todo throw exception
            return false;
        }

        self::$_CryptoModule = new $moduleClass();

        return self::$_CryptoModule;
    }
}