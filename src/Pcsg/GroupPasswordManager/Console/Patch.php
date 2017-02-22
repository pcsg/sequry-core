<?php

namespace Pcsg\GroupPasswordManager\Console;

use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use Pcsg\GroupPasswordManager\Security\Keys\Key;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use QUI;

/**
 * Console tool for HKL used patches
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Patch extends QUI\System\Console\Tool
{
    protected $_patches = array(
        'legacymacfields' => 'Setzt das MACFields-Feld in der Passwort-Tabelle auf den richtigen Wert für alte Passwörter',
        'metatable'       => 'Aktualisiert die Passwort-Meta-Tabelle für alle Benutzer'
    );

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->setName('pcsg:gpm-patch')
            ->setDescription(
                'Interne Passwort-Manager Patches - nur für Entwickler (!)'
            );
    }

    /**
     * Execute the console tool
     */
    public function execute()
    {
        QUI\Permissions\Permission::isAdmin();

        $this->writeLn('Patch-Liste:');
        $this->writeLn("\n");

        foreach ($this->_patches as $patch => $desc) {
            $this->writeLn($patch . ' :: ' . $desc);
        }

        $this->writeLn("\n");

        $this->writeLn('Patch: ');

        $patch = false;

        while ($patch === false) {
            $_patch = $this->readInput();

            if (!isset($this->_patches[$_patch])) {
                $this->writeLn('Patch existiert nicht.');
                $this->writeLn('Patch: ');
                continue;
            }

            $patch = $_patch;
        }

        switch ($patch) {
            case 'legacymacfields':
                $this->legacymacfieldsPatch();
                break;

            case 'metatable':
                $this->metatablePatch();
                break;

            default:
                $this->exitFail('Keine Methode für Patch gefunden.');
        }

        $this->exitSuccess();
    }

    protected function legacymacfieldsPatch()
    {
        try {
            $macFields = array(
                'ownerId',
                'ownerType',
                'securityClassId',
                'title',
                'description',
                'dataType',
                'cryptoData'
            );

            $macFieldsEncrypted = SymmetricCrypto::encrypt(
                json_encode($macFields),
                new Key(Utils::getSystemPasswordAuthKey())
            );

            QUI::getDataBase()->update(
                QUI::getDBTableName(Tables::PASSWORDS),
                array(
                    'MACFields' => $macFieldsEncrypted
                ),
                array(
                    'MACFields' => ''
                )
            );
        } catch (\Exception $Exception) {
            $this->exitFail(
                'Konnte MACFields-Spalte nicht schreiben: ' . $Exception->getMessage()
            );
        }
    }

    protected function metatablePatch()
    {
        try {
            $DB = QUI::getDataBase();

            // get every password
            $result = $DB->fetch(array(
                'select' => array(
                    'id'
                ),
                'from'   => QUI::getDBTableName(Tables::PASSWORDS)
            ));

            $metaTbl = QUI::getDBTableName(Tables::USER_TO_PASSWORDS_META);

            define('SYSTEM_INTERN', 1);

            foreach ($result as $row) {
                $Password = Passwords::get($row['id']);

                foreach ($Password->getAccessUserIds() as $userId) {
                    // check if entry exists
                    $check = $DB->fetch(array(
                        'from'  => $metaTbl,
                        'where' => array(
                            'userId' => $userId,
                            'dataId' => $row['id']
                        )
                    ));

                    if (!empty($check)) {
                        continue;
                    }

                    $Password->createMetaTableEntry(CryptoActors::getCryptoUser($userId));
                }
            }
        } catch (\Exception $Exception) {
            $this->exitFail(
                'Konnte Passwort-Meta-Tabelle nicht füllen: ' . $Exception->getMessage()
            );
        }
    }

    /**
     * Exits the console tool with a success msg and status 0
     *
     * @return true
     */
    protected function exitSuccess()
    {
        $this->writeLn('Patch erfolgreich ausgeführt');
        $this->writeLn("");

        return true;
    }

    /**
     * Exits the console tool with an error msg and status 1
     *
     * @param $msg
     * @return string - error msg
     */
    protected function exitFail($msg)
    {
        $this->writeLn('Skript-Abbruch wegen Fehler:');
        $this->writeLn("");
        $this->writeLn($msg);
        $this->writeLn("");
        $this->writeLn("");

        return $msg;
    }
}
