<?php

namespace Pcsg\GroupPasswordManager\Security;

use Pcsg\GroupPasswordManager\Security\Interfaces\ISymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Keys\Key;
use QUI;
use Pcsg\GroupPasswordManager\Security\HiddenString;

/**
 * This class provides a symmetric encryption API for the pcsg/grouppasswordmanager module
 */
class SymmetricCrypto
{
    const CRYPTO_MODULE = 'Halite3'; // @todo in config auslagern

    /**
     * HashWrapper Class Object for the configured hash module
     *
     * @var ISymmetricCrypto
     */
    protected static $CryptoModule = null;

    /**
     * Encrypts a plaintext string
     *
     * @param HiddenString $plainText - Data to be encrypted
     * @param Key $Key - Symmetric key
     * @return String - The Ciphertext (encrypted plaintext)
     */
    public static function encrypt(HiddenString $plainText, $Key)
    {
        $cipherText = self::getCryptoModule()->encrypt($plainText, $Key);
        return $cipherText . Utils::getCryptoModuleVersionString(self::CRYPTO_MODULE);
    }

    /**
     * Decrypts a ciphertext
     *
     * @param String $cipherText - Data to be decrypted
     * @param Key $Key - Symmetric key
     * @return HiddenString - The plaintext (decrypted ciphertext)
     */
    public static function decrypt($cipherText, $Key)
    {
        $cipherText = Utils::stripModuleVersionString($cipherText);
        return self::getCryptoModule()->decrypt($cipherText, $Key);
    }

    /**
     * Generate a new, random symmetric key
     *
     * @return Key
     */
    public static function generateKey()
    {
        $keyValue = self::getCryptoModule()->generateKey();
        return new Key(new HiddenString($keyValue));
    }

    /**
     * Get Crypto Module for symmetric encryption/decryption
     *
     * @return ISymmetricCrypto
     * @throws QUI\Exception
     */
    protected static function getCryptoModule()
    {
        if (!is_null(self::$CryptoModule)) {
            return self::$CryptoModule;
        }

        $moduleClass = '\Pcsg\GroupPasswordManager\Security\Modules\SymmetricCrypto\\';
        $moduleClass .= self::CRYPTO_MODULE;

        if (!class_exists($moduleClass)) {
            throw new QUI\Exception(
                'SymmetricCrypto :: Could not load crypto module ("'
                . $moduleClass . '").'
            );
        }

        self::$CryptoModule = new $moduleClass();

        return self::$CryptoModule;
    }
}
