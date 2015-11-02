<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\Encrypt;

use Pcsg\GroupPasswordManager\Security\Interfaces\EncryptWrapper;
use phpseclib\Crypt\AES as AESClass;

/**
 * This class provides an ecnryption API for the pcsg/grouppasswordmanager module
 *
 * AES-256
 */
class AES implements EncryptWrapper
{
    /**
     * Key size in bits
     *
     * 128-bits is sufficient
     *
     * @var Integer
     */
    const KEY_SIZE = 128;

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
        self::$_AESClass->setKeyLength(self::KEY_SIZE);

        return self::$_AESClass;
    }
}