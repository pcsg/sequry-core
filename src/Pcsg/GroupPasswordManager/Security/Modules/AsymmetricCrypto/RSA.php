<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\AsymmetricCrypto;

use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Interfaces\IAsymmetricCrypto;
use QUI;

/**
 * This class provides an ecnryption API for the pcsg/grouppasswordmanager module
 *
 * AES-256
 */
class RSA implements IAsymmetricCrypto
{
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
                'RSA :: Plaintext encryption with publiy key failed: '
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
     * @return String - The plaintext (decrypted ciphertext)
     * @throws QUI\Exception
     */
    public static function decrypt($cipherText, $privateKey)
    {
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
                'RSA :: Ciphertext decryption with private key failed: '
                . $Exception->getMessage()
            );
        }

        return $plainText;
    }

    /**
     * Generates a new, random public/private key pair
     *
     * @return Array - "privateKey" and "publicKey"
     * @throws QUI\Exception
     */
    public static function generateKeyPair()
    {
        try {
            $Res = openssl_pkey_new(array(
                'digest_alg'      => 'sha512',
                'privateKey_bits' => AsymmetricCrypto::KEY_SIZE_ENCRYPTION,
                'privateKey_type' => OPENSSL_KEYTYPE_RSA,
                'encrypt_key'     => false
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
                || empty($privateKey)
            ) {
                throw new QUI\Exception(openssl_error_string());
            }
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'RSA :: Key pair creation failed: ' . $Exception->getMessage()
            );
        }

        $keys = array(
            'publicKey'  => $publicKey['key'],
            'privateKey' => $privateKey
        );

        return $keys;
    }
}
