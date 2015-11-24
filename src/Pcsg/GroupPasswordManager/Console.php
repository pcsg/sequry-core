<?php

/**
 * This file contains \QUI\Kapitalschutz\Utils
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\gpmAuthLogin\Auth;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Encrypt;
use Pcsg\GroupPasswordManager\Security\Hash;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
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
        $this->addArgument('mode', 'genkey; setzd; getzd; editzd');
    }

    /**
     * Execute the console tool
     */
    public function execute()
    {
        $this->_userpw = $this->getArgument('pw');

        // this is only for console testing purposes
        QUI::getSession()->set(
            Auth::SESSION_ATTRIBUTE_PWHASH,
            Hash::create($this->_userpw, QUI::getUserBySession()->getId())
        );

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

            case 'editzd':
                    $this->_editZd();
                break;

            default:
                $this->writeLn("unknown mode");
                exit;
        }

        $this->writeLn("Fertig.");
    }

    protected function _genKey()
    {
        $this->writeLn("Generiere Schlüsselpaar...");
        
        $CryptoUser = new CryptoUser(
            QUI::getUserBySession()->getId(),
            CryptoAuth::getAuthPlugin('login')
        );

        $CryptoUser->generateKeyPair();

        $this->writeLn("Fertig.\n\n");
    }

    protected function _setZd()
    {
        $zd = $this->getArgument('zd');

        Manager::createCryptoData(
            "Mein Passwort",
            "Dies ist eine Passwort-Beschreibung",
            $zd
        );
    }

    protected function _getZd()
    {
        $id = $this->getArgument('zdid');

        $CryptoUser = new CryptoUser(QUI::getUserBySession()->getId());

        $this->writeLn("Entschlüssele Passwort #$id...");
        $start = explode(" ",microtime());

        $CryptoUser = $CryptoUser->getPassword($id, $this->_userpw);

        $end = explode(" ",microtime());
        $time = $end[0] - $start[0];
        $this->write(" Erfolg. (Zeit zum Entschlüsseln: " . $time . "ms)");

        $this->writeLn("Entschlüsselte Daten:\n");
        $this->writeLn($Password->getPayload());
    }

    protected function _editZd()
    {
        $id = $this->getArgument('zdid');
        $zd = $this->getArgument('zd');

        $CryptoUser = new CryptoUser(QUI::getUserBySession()->getId());

        $this->writeLn("Entschlüssele Passwort #$id...");
        $start = explode(" ",microtime());

        $Password = $CryptoUser->getPassword($id, $this->_userpw);

        $end = explode(" ",microtime());
        $time = $end[0] - $start[0];
        $this->write(" Erfolg. (Zeit zum Entschlüsseln: " . $time . "ms)");

        $Password->setPayload($zd);

        $this->writeLn("Speichere Passwort #$id mit neuen Nutzdaten...");
        $start = explode(" ",microtime());

        $Password->save();

        $end = explode(" ",microtime());
        $time = $end[0] - $start[0];
        $this->write(" Erfolg. (Zeit zum Speichern: " . $time . "ms)");
    }
}
