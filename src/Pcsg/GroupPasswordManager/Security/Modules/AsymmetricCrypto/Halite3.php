<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\AsymmetricCrypto;

use ParagonIE\Halite\Asymmetric\Crypto;
use ParagonIE\Halite\Asymmetric\EncryptionPublicKey;
use ParagonIE\Halite\Asymmetric\EncryptionSecretKey;
use ParagonIE\Halite\HiddenString;
use ParagonIE\Halite\KeyFactory;
use QUI;
use Pcsg\GroupPasswordManager\Security\Interfaces\IAsymmetricCrypto;

/**
 * This class provides a symmetric encryption API for the pcsg/grouppasswordmanager module
 *
 * Uses asymmetric encryption from paragonie/halite 3.*
 */
class Halite3 implements IAsymmetricCrypto
{
    /**
     * Encrypts a plaintext string
     *
     * @param string $plainText - Data to be encrypted
     * @param string $publicKey - Public encryption key
     * @return string - The Ciphertext (encrypted plaintext)
     * @throws \Exception
     */
    public static function encrypt($plainText, $publicKey)
    {
        try {
            $HiddenPlainText = new HiddenString($plainText);
            $HiddenPublicKey = new HiddenString($publicKey);
            $PublicKey       = new EncryptionPublicKey($HiddenPublicKey);
            $cipherText      = Crypto::seal($HiddenPlainText, $PublicKey, true);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                self::class . ' :: Plaintext encryption with publiy key failed: '
                . $Exception->getMessage()
            );
        }

        return $cipherText;
    }

    /**
     * Decrypts a ciphertext
     *
     * @param string $cipherText - Data to be decrypted
     * @param string $privateKey - Private decryption key
     * @return string - The plaintext (decrypted ciphertext)
     * @throws \Exception
     */
    public static function decrypt($cipherText, $privateKey)
    {
        try {
            $HiddenCypherText = new HiddenString($cipherText);
            $HiddenPrivateKey = new HiddenString($privateKey);
            $PrivateKey       = new EncryptionSecretKey($HiddenPrivateKey);
            $HiddenPlainText  = Crypto::unseal($HiddenCypherText, $PrivateKey, true);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                self::class . ' :: Ciphertext decryption with private key failed: '
                . $Exception->getMessage()
            );
        }

        return $HiddenPlainText->getString();
    }

    /**
     * Generates a new, random public/private key pair
     *
     * @return array - "privateKey" and "publicKey"
     * @throws QUI\Exception
     */
    public static function generateKeyPair()
    {
        try {
            $KeyPair = KeyFactory::generateEncryptionKeyPair();

            $keys = array(
                'publicKey'  => $KeyPair->getPublicKey()->getRawKeyMaterial(),
                'privateKey' => $KeyPair->getSecretKey()->getRawKeyMaterial()
            );
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                self::class . ' :: Key pair creation failed: ' . $Exception->getMessage()
            );
        }

        return $keys;
    }
}
