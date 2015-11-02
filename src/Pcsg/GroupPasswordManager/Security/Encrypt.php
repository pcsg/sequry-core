<?php

namespace Pcsg\GroupPasswordManager\Security;

use Pcsg\GroupPasswordManager\Security\Interfaces\EncryptWrapper;

/**
 * This class provides a symmetric encryption API for the pcsg/grouppasswordmanager module
 */
class Encrypt
{
    const ENCRYPTION_MODULE = 'AES'; // @todo in config auslagern

    /**
     * HashWrapper Class Object for the configured hash module
     *
     * @var EncryptWrapper
     */
    protected static $_EncryptModule = null;

    /**
     * Encrypts a plaintext string
     *
     * @param String $plainText - Data to be encrypted
     * @param String $key - Encryption key
     * @return String - The Ciphertext (encrypted plaintext)
     */
    public static function encrypt($plainText, $key)
    {
        return self::_getEncryptModule()->encrypt($plainText, $key);
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
        return self::_getEncryptModule()->decrypt($cipherText, $key);
    }

    /**
     * @return EncryptWrapper
     */
    protected static function _getEncryptModule()
    {
        if (!is_null(self::$_EncryptModule)) {
            return self::$_EncryptModule;
        }

        $moduleClass = '\Pcsg\GroupPasswordManager\Security\Modules\Encrypt\\';
        $moduleClass .= self::ENCRYPTION_MODULE;

        if (!class_exists($moduleClass)) {
            // @todo throw exception
            return false;
        }

        self::$_EncryptModule = new $moduleClass();

        return self::$_EncryptModule;
    }
}