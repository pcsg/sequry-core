<?php

/**
 * This file contains \QUI\Kapitalschutz\Utils
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Security\Encrypt;
use Pcsg\GroupPasswordManager\Security\Hash;
use QUI;

/**
 * Console tool for HKL used import
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Console extends QUI\System\Console\Tool
{
    protected $_userpw = '';

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->_LC = QUI::getLocale();

        // set Locale lang to user lang
        $this->_LC->setCurrent(
            QUI::getUserBySession()->getLang()
        );

        $this->setName('gpm:test')
            ->setDescription(
                $this->_LC->get('hklused/import', 'import.description')
            );

        $this->addArgument('pw', 'User-Passwort');
        $this->addArgument('zd', 'Zu verschlüsselnde Zugangsdaten', false, true);
        $this->addArgument('zdid', 'ID der zu entschlüsselnden Zugangsdaten', false, true);
        $this->addArgument('mode', 'genkey; setzd; getzd');
    }

    /**
     * Execute the console tool
     */
    public function execute()
    {
        $this->_userpw = $this->getArgument('pw');

        switch ($this->getArgument('mode')) {
            case 'genkey':
                    $this->_genKey();
                break;

            case 'setzd':
                    $this->_setZd();
                break;

            case 'getzd':
                    $this->_getZd();
                break;
            
            default:
                $this->writeLn("unknown mode");
                exit;
        }

        $this->writeLn("Fertig.");
    }

    protected function _genKey()
    {
        $this->writeLn("Generiere Schlüsselpaar mit Passwort: " . $this->_userpw);

        $Res = openssl_pkey_new(array(
            'digest_alg' => 'sha512',
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'encrypt_key' => true,
            'ecnrypt_key_cipher' => OPENSSL_CIPHER_AES_128_CBC
        ));

        $publicKey = openssl_pkey_get_details($Res);
        $publicKey = $publicKey['key'];

        $userPassHash = Hash::createHash($this->_userpw, 'salt'); // @todo korrekter salt
        openssl_pkey_export($Res, $privateKey, $this->_userpw);

        $encryptedPrivateKey = Encrypt::encrypt(
            $privateKey,
            $userPassHash
        );

        QUI::getDataBase()->insert(
            'pcsg_gpm_users',
            array(
                'user_id' => QUI::getUserBySession()->getId(),
                'public_key' => $publicKey,
                'private_key' => $encryptedPrivateKey
            )
        );
    }

    protected function _setZd()
    {
        $zd = $this->getArgument('zd');

        $newPass = PasswordManager::create(
            "Mein Passwort",
            "Dies ist eine Passwort-Beschreibung",
            $zd
        );

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'public_key'
            ),
            'from' => 'pcsg_gpm_users',
            'where' => array(
                'user_id' => QUI::getUserBySession()->getId()
            )
        ));

        $publicKey = $result[0]['public_key'];

//        $ciphertext = '';
        openssl_public_encrypt($newPass['key'], $ciphertext, $publicKey);

        QUI::getDataBase()->insert(
            'pcsg_gpm_user_passwords',
            array(
                'user_id' => QUI::getUserBySession()->getId(),
                'password_id' => $newPass['id'],
                'password_key' => $ciphertext
            )
        );
    }

    protected function _getZd()
    {
        $id = $this->getArgument('zdid');

        // get private key of user
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'private_key'
            ),
            'from' => 'pcsg_gpm_users',
            'where' => array(
                'user_id' => QUI::getUserBySession()->getId()
            )
        ));

        $privateKeyEncrypted = $result[0]['private_key'];

        $privateKeyProtected = Encrypt::decrypt(
            $privateKeyEncrypted,
            Hash::createHash($this->_userpw, 'salt') // @todo korrekter salt
        );

        $Res = openssl_pkey_get_private(
            $privateKeyProtected,
            $this->_userpw
        );

        openssl_pkey_export($Res, $privateKey);

        // get encrypted password data
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'password_key'
            ),
            'from' => 'pcsg_gpm_user_passwords',
            'where' => array(
                'user_id' => QUI::getUserBySession()->getId(),
                'password_id' => $id
            )
        ));

        $passwordKeyEncrypted = $result[0]['password_key'];

        openssl_private_decrypt(
            $passwordKeyEncrypted,
            $passwordKey,
            $privateKey
        );

        $password = PasswordManager::get($id, $passwordKey);

        \QUI\System\Log::writeRecursive( "passwort ausgabe:::" );
        \QUI\System\Log::writeRecursive( $password );
    }
}
