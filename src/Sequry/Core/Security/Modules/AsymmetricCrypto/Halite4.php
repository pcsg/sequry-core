<?php

namespace Sequry\Core\Security\Modules\AsymmetricCrypto;

use ParagonIE\Halite\Asymmetric\Crypto;
use ParagonIE\Halite\Asymmetric\EncryptionPublicKey;
use ParagonIE\Halite\Asymmetric\EncryptionSecretKey;
use ParagonIE\Halite\HiddenString as ParagonieHiddenString;
use ParagonIE\Halite\KeyFactory;
use Sequry\Core\Security\Keys\KeyPair;
use QUI;
use Sequry\Core\Security\Interfaces\IAsymmetricCrypto;
use Sequry\Core\Security\HiddenString;

/**
 * This class provides a symmetric encryption API for the sequry/core module
 *
 * Uses asymmetric encryption from paragonie/halite 4.*
 */
class Halite4 implements IAsymmetricCrypto
{
    /**
     * Encrypts a plaintext string
     *
     * @param HiddenString $plainText - Data to be encrypted
     * @param KeyPair $KeyPair - Encryption KeyPair
     * @return string - The Ciphertext (encrypted plaintext)
     * @throws \Exception
     */
    public static function encrypt(HiddenString $plainText, KeyPair $KeyPair)
    {
        try {
            $HiddenPlainText = new ParagonieHiddenString($plainText->getString());
            $HiddenPublicKey = new ParagonieHiddenString($KeyPair->getPublicKey()->getValue()->getString());
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
     * @param KeyPair $KeyPair - Decryption KeyPair
     * @return HiddenString - The plaintext (decrypted ciphertext)
     * @throws \Exception
     */
    public static function decrypt($cipherText, KeyPair $KeyPair)
    {
        try {
            $HiddenCypherText = new ParagonieHiddenString($cipherText);
            $HiddenPrivateKey = new ParagonieHiddenString($KeyPair->getPrivateKey()->getValue()->getString());
            $PrivateKey       = new EncryptionSecretKey($HiddenPrivateKey);
            $HiddenPlainText  = Crypto::unseal($HiddenCypherText, $PrivateKey, true);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                self::class . ' :: Ciphertext decryption with private key failed: '
                . $Exception->getMessage()
            );
        }

        return new HiddenString($HiddenPlainText->getString());
    }

    /**
     * Generates a new, random public/private key pair
     *
     * @return KeyPair
     * @throws QUI\Exception
     */
    public static function generateKeyPair()
    {
        try {
            $GeneratedKeyPair = KeyFactory::generateEncryptionKeyPair();

            $KeyPair = new KeyPair(
                new HiddenString($GeneratedKeyPair->getPublicKey()->getRawKeyMaterial()),
                new HiddenString($GeneratedKeyPair->getSecretKey()->getRawKeyMaterial())
            );
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                self::class . ' :: Key pair creation failed: ' . $Exception->getMessage()
            );
        }

        return $KeyPair;
    }
}
