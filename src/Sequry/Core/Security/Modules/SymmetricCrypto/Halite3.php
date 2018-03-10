<?php

namespace Sequry\Core\Security\Modules\SymmetricCrypto;

use ParagonIE\Halite\KeyFactory;
use QUI;
use ParagonIE\Halite\Symmetric\Crypto;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use Sequry\Core\Security\Interfaces\ISymmetricCrypto;
use Sequry\Core\Security\HiddenString;
use ParagonIE\Halite\HiddenString as ParagonieHiddenString;
use Sequry\Core\Security\Keys\Key;

/**
 * This class provides an ecnryption API for the sequry/core module
 *
 * Uses symmetric encryption from paragonie/halite 3.*
 */
class Halite3 implements ISymmetricCrypto
{
    /**
     * Encrypts a plaintext string
     *
     * @param HiddenString $plainText - Data to be encrypted
     * @param Key $Key - Encryption key
     * @return String - The Ciphertext (encrypted plaintext)
     * @throws QUI\Exception
     */
    public static function encrypt(HiddenString $plainText, Key $Key)
    {
        try {
            $HiddenPlainText = new ParagonieHiddenString($plainText->getString());
            $HiddenKey       = new ParagonieHiddenString($Key->getValue());
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
     * @param Key $Key - Decryption key
     * @return String - The plaintext (decrypted ciphertext)
     * @throws QUI\Exception
     */
    public static function decrypt($cipherText, Key $Key)
    {
        try {
            $HiddenCipherText = new ParagonieHiddenString($cipherText);
            $HiddenKey        = new ParagonieHiddenString($Key->getValue());
            $SecretKey        = new EncryptionKey($HiddenKey);
            $HiddenPlainText  = Crypto::decrypt($HiddenCipherText, $SecretKey, true);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                self::class . ' :: Ciphertext decryption failed: '
                . $Exception->getMessage()
            );
        }

        return new HiddenString($HiddenPlainText->getString());
    }
}
