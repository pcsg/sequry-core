<?php

/**
 * This file contains \QUI\Kapitalschutz\Utils
 */

namespace Pcsg\GroupPasswordManager;

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
        $this->addArgument('mode', 'genkey; setzd; getzd');
    }

    /**
     * Execute the console tool
     */
    public function execute()
    {
        $this->_userpw = $this->getArgument('pw');

        QUI::getSession()->set(CryptoUser::ATTRIBUTE_PWHASH, $this->_userpw); // this is only for console testing purposes

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
        $CryptoUser = new CryptoUser(QUI::getUserBySession()->getId());

        $this->writeLn("Generiere Schlüsselpaar...");
        $CryptoUser->generateKeyPair();
        $this->writeLn("Fertig.");
    }

    protected function _setZd()
    {
        $zd = $this->getArgument('zd');

        Manager::createPassword(
            "Mein Passwort",
            "Dies ist eine Passwort-Beschreibung",
            $zd
        );
    }

    protected function _getZd()
    {
        $id = $this->getArgument('zdid');

        $CryptoUser = new CryptoUser(QUI::getUserBySession()->getId());

        $Password = $CryptoUser->getPassword($id, $this->_userpw);

        \QUI\System\Log::writeRecursive( $Password->getPayload() );
    }
}
