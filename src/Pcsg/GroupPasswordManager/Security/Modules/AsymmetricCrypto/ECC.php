<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\AsymmetricCrypto;

use ParagonIE\Halite\Asymmetric\Crypto;
use ParagonIE\Halite\Asymmetric\EncryptionPublicKey;
use ParagonIE\Halite\Asymmetric\EncryptionSecretKey;
use ParagonIE\Halite\KeyFactory;
use QUI;
use Pcsg\GroupPasswordManager\Security\Interfaces\iAsymmetricCrypto;

/**
 * This class provides an ecnryption API for the pcsg/grouppasswordmanager module
 *
 * ECC - Ellicptic Curce Cryptography (Curve25519)
 */
class ECC implements iAsymmetricCrypto
{
    /**
     * Encrypts a plaintext string
     *
     * @param string $plainText - Data to be encrypted
     * @param string $publicKey - Public encryption key
     * @return string - The Ciphertext (encrypted plaintext)
     * @throws QUI\Exception
     */
    public static function encrypt($plainText, $publicKey)
    {
        try {
            $PublicKey = new EncryptionPublicKey($publicKey);
            $cipherText = Crypto::seal($plainText, $PublicKey, true);
        } catch (QUI\Exception $Exception) {
            throw new QUI\Exception(
                'ECC :: Plaintext encryption with publiy key failed: '
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
     * @throws QUI\Exception
     */
    public static function decrypt($cipherText, $privateKey)
    {
        try {
            $PrivateKey = new EncryptionSecretKey($privateKey);
            $plainText = Crypto::unseal($cipherText, $PrivateKey, true);
        } catch (QUI\Exception $Exception) {
            throw new QUI\Exception(
                'ECC :: Ciphertext decryption with private key failed: '
                . $Exception->getMessage()
            );
        }

        return $plainText;
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
                'publicKey' => $KeyPair->getPublicKey()->get(),
                'privateKey' => $KeyPair->getSecretKey()->get()
            );
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'ECC :: Key pair creation failed: ' . $Exception->getMessage()
            );
        }

        return $keys;
    }
}