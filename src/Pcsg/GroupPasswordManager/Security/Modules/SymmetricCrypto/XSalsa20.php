<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\SymmetricCrypto;

use ParagonIE\Halite\KeyFactory;
use QUI;
use ParagonIE\Halite\Symmetric\Crypto;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use Pcsg\GroupPasswordManager\Security\Interfaces\iSymmetricCrypto;

/**
 * This class provides an ecnryption API for the pcsg/grouppasswordmanager module
 *
 * XSalsa20 stream cipher
 */
class XSalsa20 implements iSymmetricCrypto
{
    /**
     * Encrypts a plaintext string
     *
     * @param String $plainText - Data to be encrypted
     * @param String $key - Encryption key
     * @return String - The Ciphertext (encrypted plaintext)
     * @throws QUI\Exception
     */
    public static function encrypt($plainText, $key)
    {
        try {
            $SecretKey = new EncryptionKey($key);
            $cipherText = Crypto::encrypt($plainText, $SecretKey, true);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'XSalsa20 :: Plaintext encryption failed: '
                . $Exception->getMessage()
            );
        }

        return $cipherText;
    }

    /**
     * Generate a new, random symmetric key
     *
     * @return String - The random key
     * @throws QUI\Exception
     */
    public static function generateKey()
    {
        try {
            $SecretKey = KeyFactory::generateEncryptionKey();
            $secretKey = $SecretKey->get();
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'XSalsa20 :: Random key generation failed: '
                . $Exception->getMessage()
            );
        }

        return $secretKey;
    }

    /**
     * Decrypts a ciphertext
     *
     * @param String $cipherText - Data to be decrypted
     * @param String $key - Decryption key
     * @return String - The plaintext (decrypted ciphertext)
     * @throws QUI\Exception
     */
    public static function decrypt($cipherText, $key)
    {
        try {
            $SecretKey = new EncryptionKey($key);
            $plainText = Crypto::encrypt($cipherText, $SecretKey, true);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'XSalsa20 :: Ciphertext decryption failed: '
                . $Exception->getMessage()
            );
        }

        return $plainText;
    }
}