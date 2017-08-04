<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Interfaces\ISymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use phpseclib\Crypt\AES as AESClass;
/**
 * This class provides an ecnryption API for the pcsg/grouppasswordmanager module
 *
 * AES-256
 */
class AES implements ISymmetricCrypto
{
    /**
     * Encryption Mode
     *
     * @var String - MODE_ + ECB (not recommended), CBC, CTR, OFB or CFB
     */
    const ENCRYPTION_MODE = 'MODE_CBC';

    /**
     * Instance of phpseclib AES Class
     *
     * @var AESClass
     */
    protected static $AESClass = null;

    /**
     * Encrypts a plaintext string
     *
     * @param String $plainText - Data to be encrypted
     * @param String $key - Encryption key
     * @return String - The Ciphertext (encrypted plaintext)
     */
    public static function encrypt($plainText, $key)
    {
        $AESClass = self::getAESClass();
        $AESClass->setKey($key);
        return $AESClass->encrypt($plainText);
    }

    public static function generateKey()
    {
        // TODO: Implement generateKey() method.
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
        $AESClass = self::getAESClass();
        $AESClass->setKey($key);
        return $AESClass->decrypt($cipherText);
    }

    /**
     * Returns an instance of the phpseclib/AES Class
     *
     * @return AESClass
     */
    protected static function getAESClass()
    {
        if (!is_null(self::$AESClass)) {
            return self::$AESClass;
        }
        self::$AESClass = new AESClass(self::ENCRYPTION_MODE);
        self::$AESClass->setKeyLength(128);
        return self::$AESClass;
    }
}
