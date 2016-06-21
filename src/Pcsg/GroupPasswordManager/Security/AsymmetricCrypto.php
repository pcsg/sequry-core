<?php

namespace Pcsg\GroupPasswordManager\Security;

use Pcsg\GroupPasswordManager\Security\Interfaces\iAsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Keys\KeyPair;
use QUI;
use Pcsg\GroupPasswordManager\Security\Interfaces\AsymmetricCryptoWrapper;

/**
 * This class provides a symmetric encryption API for the pcsg/grouppasswordmanager module
 */
class AsymmetricCrypto
{
    const CRYPTO_MODULE = 'ECC'; // @todo in config auslagern


    // @todo diese konstanten entfernen, da sich das baustein-modul darum kümmert

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
     * @var iAsymmetricCrypto
     */
    protected static $CryptoModule = null;

    /**
     * Encrypts a plaintext string
     *
     * @param string $plainText - Data to be encrypted
     * @param string $publicKey - Public encryption key
     * @return string - The Ciphertext (encrypted plaintext)
     */
    public static function encrypt($plainText, $publicKey)
    {
        return self::getCryptoModule()->encrypt($plainText, $publicKey);
    }

    /**
     * Decrypts a ciphertext
     *
     * @param string $cipherText - Data to be decrypted
     * @param string $privateKey - Private decryption key
     * @return string - The plaintext (decrypted ciphertext)
     */
    public static function decrypt($cipherText, $privateKey)
    {
        return self::getCryptoModule()->decrypt($cipherText, $privateKey);
    }

    /**
     * Generates a new public/private key pair
     *
     * @return KeyPair
     * @throws QUI\Exception
     */
    public static function generateKeyPair()
    {
        // Generate key pair
        try {
            $keyPair = self::getCryptoModule()->generateKeyPair();

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

        return new KeyPair($keyPair['publicKey'], $keyPair['privateKey']);
    }

    /**
     * Checks if a public/private key pair is correct
     *
     * @param string $publicKey
     * @param string $privateKey
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
     * @return iAsymmetricCrypto
     * @throws QUI\Exception
     */
    protected static function getCryptoModule()
    {
        if (!is_null(self::$CryptoModule)) {
            return self::$CryptoModule;
        }

        $moduleClass = '\Pcsg\GroupPasswordManager\Security\Modules\AsymmetricCrypto\\';
        $moduleClass .= self::CRYPTO_MODULE;

        if (!class_exists($moduleClass)) {
            throw new QUI\Exception(
                'AsymmetricCrypto :: Could not load crypto module ("'
                . $moduleClass . '").'
            );
        }

        self::$CryptoModule = new $moduleClass();

        return self::$CryptoModule;
    }
}