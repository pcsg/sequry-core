<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\SymmetricCrypto;

use Pcsg\GroupPasswordManager\Security\Interfaces\iSymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use phpseclib\Crypt\AES as AESClass;

/**
 * This class provides an ecnryption API for the pcsg/grouppasswordmanager module
 *
 * AES-256
 */
class AES implements iSymmetricCrypto
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
    protected static $_AESClass = null;

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
        if (!is_null(self::$_AESClass)) {
            return self::$_AESClass;
        }

        self::$_AESClass = new AESClass(self::ENCRYPTION_MODE);
        self::$_AESClass->setKeyLength(SymmetricCrypto::KEY_SIZE_ENCRYPTION);

        return self::$_AESClass;
    }
}