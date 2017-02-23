<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\SymmetricCrypto;

use ParagonIE\Halite\HiddenString;
use ParagonIE\Halite\KeyFactory;
use QUI;
use ParagonIE\Halite\Symmetric\Crypto;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use Pcsg\GroupPasswordManager\Security\Interfaces\ISymmetricCrypto;

/**
 * This class provides an ecnryption API for the pcsg/grouppasswordmanager module
 *
 * Uses symmetric encryption from paragonie/halite 3.*
 */
class Halite3 implements ISymmetricCrypto
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
            $HiddenPlainText = new HiddenString($plainText);
            $HiddenKey       = new HiddenString($key);
            $SecretKey       = new EncryptionKey($HiddenKey);
            $cipherText      = Crypto::encrypt($HiddenPlainText, $SecretKey, true);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                self::class . ' :: Plaintext encryption failed: '
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
            $secretKey = $SecretKey->getRawKeyMaterial();
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                self::class . ' :: Random key generation failed: '
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
            $HiddenCipherText = new HiddenString($cipherText);
            $HiddenKey        = new HiddenString($key);
            $SecretKey        = new EncryptionKey($HiddenKey);
            $HiddenPlainText  = Crypto::decrypt($HiddenCipherText, $SecretKey, true);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                self::class . ' :: Ciphertext decryption failed: '
                . $Exception->getMessage()
            );
        }

        return $HiddenPlainText->getString();
    }
}