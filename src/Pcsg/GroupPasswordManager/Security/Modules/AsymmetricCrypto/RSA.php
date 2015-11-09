<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\AsymmetricCrypto;

use QUI;
use Pcsg\GroupPasswordManager\Security\Interfaces\AsymmetricCryptoWrapper;

/**
 * This class provides an ecnryption API for the pcsg/grouppasswordmanager module
 *
 * AES-256
 */
class RSA implements AsymmetricCryptoWrapper
{
    /**
     * Key size in bits
     *
     * @var Integer
     */
    const KEY_SIZE = 4096;

    /**
     * Encrypts a plaintext string
     *
     * @param String $plainText - Data to be encrypted
     * @param String $publicKey - Public encryption key
     * @return String - The Ciphertext (encrypted plaintext)
     * @throws QUI\Exception
     */
    public static function encrypt($plainText, $publicKey)
    {
        try {
            $encrypt = openssl_public_encrypt($plainText, $cipherText, $publicKey);

            if ($encrypt === false) {
                throw new QUI\Exception(openssl_error_string());
            }
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'Plaintext encryption with publiy key failed: '
                . $Exception->getMessage()
            );
        }

        return $cipherText;
    }

    /**
     * Decrypts a ciphertext
     *
     * @param String $cipherText - Data to be decrypted
     * @param String $privateKey - Private decryption key
     * @param String $password (optional) - Password for private key
     * @return String - The plaintext (decrypted ciphertext)
     * @throws QUI\Exception
     */
    public static function decrypt($cipherText, $privateKey, $password = null)
    {
        // Try to get private key if password protected
        if (!is_null($password)) {
            try {
                $Res = openssl_pkey_get_private($privateKey, $password);

                if ($Res === false) {
                    throw new QUI\Exception(openssl_error_string());
                }

                $getPrivateKey = openssl_pkey_export($Res, $privateKey);

                if ($getPrivateKey === false) {
                    throw new QUI\Exception(openssl_error_string());
                }
            } catch (\Exception $Exception) {
                throw new QUI\Exception(
                    'Private key could not be decrypted: '
                    . $Exception->getMessage()
                );
            }
        }

        try {
            $decrypt = openssl_private_decrypt(
                $cipherText,
                $plainText,
                $privateKey
            );

            if ($decrypt === false) {
                throw new QUI\Exception(openssl_error_string());
            }
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'Ciphertext decryption with private key failed: '
                . $Exception->getMessage()
            );
        }
        
        return $plainText;
    }

    /**
     * Generates a new public/private key pair
     *
     * @param String $password (optional) - Password to protect the private key
     * @return Array - "privateKey" and "publicKey"
     * @throws QUI\Exception
     */
    public static function generateKeyPair($password = null)
    {
        try {
            $Res = openssl_pkey_new(array(
                'digest_alg' => 'sha512',
                'privateKey_bits' => self::KEY_SIZE,
                'privateKey_type' => OPENSSL_KEYTYPE_RSA,
                'encrypt_key' => !is_null($password),
                'encrypt_key_cipher' => OPENSSL_CIPHER_AES_128_CBC
            ));

            if ($Res === false) {
                throw new QUI\Exception(openssl_error_string());
            }

            $publicKey = openssl_pkey_get_details($Res);

            if ($publicKey === false) {
                throw new QUI\Exception(openssl_error_string());
            }

            $privateKeyExport = openssl_pkey_export(
                $Res,
                $privateKey,
                $password
            );

            if ($privateKeyExport === false
                || empty($privateKey)) {
                throw new QUI\Exception(openssl_error_string());
            }
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'Key pair creation failed: ' . $Exception->getMessage()
            );
        }

        $keys = array(
            'publicKey' => $publicKey['key'],
            'privateKey' => $privateKey
        );

        return $keys;
    }
}