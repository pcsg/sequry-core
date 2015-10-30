<?php

/**
 * This file contains \QUI\Kapitalschutz\Utils
 */

namespace Pcsg\GroupPasswordManager;

use FontLib\Table\Type\loca;
use Pcsg\GroupPasswordManager\Security\Password;
use Pcsg\GroupPasswordManager\Security\Scrypt;
use QUI;
use QUI\Utils\System\File as File;
use QUI\Projects\Media\Utils as MediaUtils;
use Hklused\Machines;
use Hklused\Machines\CategoryManager as CM;
use SimpleExcel\SimpleExcel;

/**
 * Console tool for HKL used import
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Console extends QUI\System\Console\Tool
{
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

        // add arguments
//        $this->addArgument(
//            'machines',
//            $this->_LC->get(
//                'hklused/import',
//                'import.params.machines.description'
//            ),
//            'm',
//            true
//        );
    }

    /**
     * Execute the console tool
     */
    public function execute()
    {
        $this->writeLn("Generiere Hash aus Passwort");

        $hash = Scrypt::hash(
            'pferd'
        );

        $this->writeLn("Hash: " . $hash);

        $this->writeLn("Generiere Schlüsselpaar");

        $Res = openssl_pkey_new(array(
            'digest_alg' => 'sha512',
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'encrypt_key' => true
        ));

        openssl_pkey_export($Res, $privateKey);

        $this->writeLn("Private key: " . $privateKey);
    }
}
