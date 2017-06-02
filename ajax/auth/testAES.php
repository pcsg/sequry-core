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
    function ($cipher) {
        $binary = hex2bin($cipher);

        $commKey = Authentication::getSessionCommunicationKey();

        $algo = 'aes-128-cbc';
    //        $iv   = random_bytes(openssl_cipher_iv_length($algo));
        $iv = $commKey['iv'];

    //        $encrypt = openssl_encrypt(
    //            'Pferde sind super!',
    //            $algo,
    //            $commKey,
    //            OPENSSL_RAW_DATA,
    //            $iv
    //        );

        // Change 1 bit in ciphertext
    // $i = rand(0, mb_strlen($ciphertext, '8bit') - 1);
    // $ciphertext[$i] = $ciphertext[$i] ^ chr(1);
        $decrypt = openssl_decrypt(
            $binary,
            $algo,
            $commKey['key'],
            OPENSSL_RAW_DATA,
            $iv
        );

    //        /*** TEST ***/
    //        require([
    //            'aesJS',
    //            'Ajax'
    //        ], function (aesJS, QUIAjax) {
    //            Authentication.getCommKey().then(function (commKey) {
    //
    //                console.log(commKey);
    //
    //                var textBytes      = aesJS.utils.utf8.toBytes('Pferde sind schneller als du!');
    //                var textBytesPadded = aesJS.padding.pkcs7.pad(textBytes);
    //
    //                var aesCbc         = new aesJS.ModeOfOperation.cbc(commKey.key, commKey.iv);
    //                var encryptedBytes = aesCbc.encrypt(textBytesPadded);
    //
    //                var encryptedHex = aesJS.utils.hex.fromBytes(encryptedBytes);
    //
    //                //console.log(encryptedHex);
    //
    //                //aesCbc         = new aesJS.ModeOfOperation.cbc(commKey.key, commKey.iv);
    //                //var decryptedBytes = aesCbc.decrypt(encryptedBytes);
    //                //
    //                // //Convert our bytes back into text
    //                //var decryptedText = aesJS.padding.pkcs7.strip(decryptedBytes);
    //                //var decryptedTextUnpadded = aesJS.utils.utf8.fromBytes(decryptedText);
    //                //
    //                //console.log(decryptedTextUnpadded);
    //
    //
    //
    //                QUIAjax.post(
    //                    'package_pcsg_grouppasswordmanager_ajax_auth_testAES',
    //                    function () {
    //
    //                    }, {
    //                            'package': 'pcsg/grouppasswordmanager',
    //                            cipher   : encryptedHex
    //                        }
    //                    );
    //                });
    //        });
    //            /************/
    },
    array('cipher'),
    'Permission::checkAdminUser'
);
