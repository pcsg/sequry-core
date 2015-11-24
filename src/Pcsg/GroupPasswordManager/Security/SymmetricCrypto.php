<?php

namespace Pcsg\GroupPasswordManager\Security;

use QUI;
use Pcsg\GroupPasswordManager\Security\Interfaces\SymmetricCryptoWrapper;

/**
 * This class provides a symmetric encryption API for the pcsg/grouppasswordmanager module
 */
class SymmetricCrypto
{
    const CRYPTO_MODULE = 'XSalsa20'; // @todo in config auslagern

    /**
     * Key size of symmetric key used for en/decryption [bits]
     */
    const KEY_SIZE_ENCRYPTION = 256;

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
     * Generate a new, random symmetric key
     *
     * @return String - The random key
     */
    public static function generateKey()
    {
        return self::_getCryptoModule()->generateKey();
    }

    /**
     * Get Crypto Module for symmetric encryption/decryption
     *
     * @return SymmetricCryptoWrapper
     * @throws QUI\Exception
     */
    protected static function _getCryptoModule()
    {
        if (!is_null(self::$_CryptoModule)) {
            return self::$_CryptoModule;
        }

        $moduleClass = '\Pcsg\GroupPasswordManager\Security\Modules\SymmetricCrypto\\';
        $moduleClass .= self::CRYPTO_MODULE;

        if (!class_exists($moduleClass)) {
            throw new QUI\Exception(
                'SymmetricCrypto :: Could not load crypto module ("'
                . $moduleClass . '").'
            );
        }

        self::$_CryptoModule = new $moduleClass();

        return self::$_CryptoModule;
    }
}