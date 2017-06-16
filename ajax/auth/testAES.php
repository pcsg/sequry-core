<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Get the symmetric key that is used for encryption
 * between frontend and backend for the current session
 *
 * @return false|string
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_auth_testAES',
    function ($pubKey) {
//        $binary = hex2bin($cipher);
//
        $commKey = Authentication::getSessionCommunicationKey();
//        $pubKey = hex2bin($pubKey);
//
        $algo = 'aes-128-cbc';
//    //        $iv   = random_bytes(openssl_cipher_iv_length($algo));
//        $iv = $commKey['iv'];
//

//        $k = "-----BEGIN RSA PUBLIC KEY-----";
//        $k .= "\n" . $pubKey . "\n";
//        $k .= "-----END RSA PUBLIC KEY-----\n";

        $k = "-----BEGIN PUBLIC KEY-----\n" .
        chunk_split(base64_encode($pubKey), 64, "\n") .
        "-----END PUBLIC KEY-----\n";

        \QUI\System\Log::writeRecursive($k);

        $PubKey = openssl_pkey_get_public($k);

        \QUI\System\Log::writeRecursive(openssl_error_string());

        openssl_public_encrypt(
            json_encode($commKey),
            $encrypt,
            $PubKey
        );

//        \QUI\System\Log::writeRecursive($commKey);

        return bin2hex($encrypt);

//            $encrypt = openssl_encrypt(
//                $commKey,
//                $algo,
//                $commKey['key'],
//                OPENSSL_RAW_DATA,
//                $commKey['iv']
//            );
//
//        // Change 1 bit in ciphertext
//    // $i = rand(0, mb_strlen($ciphertext, '8bit') - 1);
//    // $ciphertext[$i] = $ciphertext[$i] ^ chr(1);
//        $decrypt = openssl_decrypt(
//            $binary,
//            $algo,
//            $commKey['key'],
//            OPENSSL_RAW_DATA,
//            $iv
//        );
//
//        \QUI\System\Log::writeRecursive($decrypt);



                /************/





    },
    array('pubKey'),
    'Permission::checkAdminUser'
);
