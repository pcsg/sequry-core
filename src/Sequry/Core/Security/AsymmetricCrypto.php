<?php

namespace Sequry\Core\Security;

use Sequry\Core\Security\Interfaces\IAsymmetricCrypto;
use Sequry\Core\Security\Keys\KeyPair;
use QUI;

/**
 * This class provides a symmetric encryption API for the sequry/core module
 */
class AsymmetricCrypto
{
    const CRYPTO_MODULE = 'Halite4'; // @todo in config auslagern

    /**
     * HashWrapper Class Object for the configured hash module
     *
     * @var IAsymmetricCrypto
     */
    protected static $CryptoModule = null;

    /**
     * Encrypts a plaintext string
     *
     * @param HiddenString $plainText - Data to be encrypted
     * @param KeyPair $KeyPair
     * @return string - The Ciphertext (encrypted plaintext)
     */
    public static function encrypt($plainText, $KeyPair)
    {
        $cipherText = self::getCryptoModule()->encrypt($plainText, $KeyPair);
        return $cipherText . Utils::getCryptoModuleVersionString(self::CRYPTO_MODULE);
    }

    /**
     * Decrypts a ciphertext
     *
     * @param string $cipherText - Data to be decrypted
     * @param KeyPair $KeyPair
     * @return HiddenString - The plaintext (decrypted ciphertext)
     */
    public static function decrypt($cipherText, $KeyPair)
    {
        $cipherText = Utils::stripModuleVersionString($cipherText);
        return self::getCryptoModule()->decrypt($cipherText, $KeyPair);
    }

    /**
     * Generates a new public/private key pair
     *
     * @return KeyPair
     * @throws QUI\Exception
     */
    public static function generateKeyPair()
    {
        // Generate KeyPair
        try {
            $KeyPair = self::getCryptoModule()->generateKeyPair();
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'Could not generate key pair: ' . $Exception->getMessage()
            );
        }

        // Test validity of KeyPair
        if (!self::testKeyPair($KeyPair)) {
            throw new QUI\Exception(
                'Could not generate KeyPair: KeyPair test failed.'
            );
        }

        return $KeyPair;
    }

    /**
     * Checks if a public/private key pair is correct
     *
     * @param KeyPair $KeyPair
     * @return Boolean - validity of the key pair
     */
    public static function testKeyPair($KeyPair)
    {
        $rnd = new HiddenString(uniqid('', true));

        $cipherText = self::encrypt($rnd, $KeyPair);
        $plainText  = self::decrypt($cipherText, $KeyPair);

        return MAC::compare($rnd->getString(), $plainText->getString());
    }

    /**
     * Get Crypto Module for symmetric encryption/decryption
     *
     * @return IAsymmetricCrypto
     * @throws QUI\Exception
     */
    protected static function getCryptoModule()
    {
        if (!is_null(self::$CryptoModule)) {
            return self::$CryptoModule;
        }

        $moduleClass = '\Sequry\Core\Security\Modules\AsymmetricCrypto\\';
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
