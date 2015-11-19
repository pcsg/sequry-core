<?php

namespace Pcsg\GroupPasswordManager\Security;

use QUI;
use Pcsg\GroupPasswordManager\Security\Interfaces\AsymmetricCryptoWrapper;

/**
 * This class provides a symmetric encryption API for the pcsg/grouppasswordmanager module
 */
class AsymmetricCrypto
{
    const CRYPTO_MODULE = 'ECC'; // @todo in config auslagern

    /**
     * Key size of public/private key used for en/decryption [bits]
     */
    const KEY_SIZE_ENCRYPTION = 4096;

    /**
     * Key size of public/private key used for authentification [bits]
     */
    const KEY_SIZE_AUTHENTIFICATION = 4096;

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
     * @return String - The plaintext (decrypted ciphertext)
     */
    public static function decrypt($cipherText, $privateKey)
    {
        return self::_getCryptoModule()->decrypt($cipherText, $privateKey);
    }

    /**
     * Generates a new public/private key pair
     *
     * @return Array - "privateKey" and "publicKey"
     * @throws QUI\Exception
     */
    public static function generateKeyPair()
    {
        // Generate key pair
        try {
            $keyPair = self::_getCryptoModule()->generateKeyPair();

            if (!isset($keyPair['publicKey'])
                || empty($keyPair['publicKey'])) {
                throw new QUI\Exception(
                    'Public key was not generated or empty.'
                );
            }

            if (!isset($keyPair['privateKey'])
                || empty($keyPair['privateKey'])) {
                throw new QUI\Exception(
                    'Private key was not generated or empty.'
                );
            }
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'Could not generate key pair: ' . $Exception->getMessage()
            );
        }

        // Test validity of key pair
        if (!self::testKeyPair($keyPair['publicKey'], $keyPair['privateKey'])) {
            throw new QUI\Exception(
                'Could not generate key pair: Key pair test failed.'
            );
        }

        \QUI\System\Log::writeRecursive( mb_strlen($keyPair['privateKey'], '8bit') );

        return $keyPair;
    }

    /**
     * Checks if a public/private key pair is correct
     *
     * @param String $publicKey
     * @param String $privateKey
     * @return Boolean - validity of the key pair
     */
    public static function testKeyPair($publicKey, $privateKey)
    {
        $rnd = uniqid('', true);

        $cipherText = self::encrypt($rnd, $publicKey);
        $plainText = self::decrypt($cipherText, $privateKey);

        return Utils::compareStrings($rnd, $plainText);
    }

    /**
     * Get Crypto Module for symmetric encryption/decryption
     *
     * @return AsymmetricCryptoWrapper
     * @throws QUI\Exception
     */
    protected static function _getCryptoModule()
    {
        if (!is_null(self::$_CryptoModule)) {
            return self::$_CryptoModule;
        }

        $moduleClass = '\Pcsg\GroupPasswordManager\Security\Modules\AsymmetricCrypto\\';
        $moduleClass .= self::CRYPTO_MODULE;

        if (!class_exists($moduleClass)) {
            throw new QUI\Exception(
                'AsymmetricCrypto :: Could not load crypto module ("'
                . $moduleClass . '").'
            );
        }

        self::$_CryptoModule = new $moduleClass();

        return self::$_CryptoModule;
    }
}