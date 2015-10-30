<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\Encrypt;

use Pcsg\GroupPasswordManager\Security\Interfaces\EncryptWrapper;
use phpseclib\Crypt\AES as AESClass;

/**
 * This class provides a hashing API for the pcsg/grouppasswordmanager module
 */
class AES implements EncryptWrapper
{
    /**
     * Creates a hash value from a given string
     *
     * @param String $plainText - Data to be encrypted
     * @param String $key - Encryption key
     */
    public static function encrypt($plainText, $key)
    {

    }

    /**
     * Compares two hashes
     *
     * @param String $cipherText - Data to be decrypted
     * @param String $key - Decryption key
     */
    public static function decrypt($cipherText, $key)
    {

    }
}