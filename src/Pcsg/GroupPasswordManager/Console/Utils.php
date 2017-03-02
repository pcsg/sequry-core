<?php

namespace Pcsg\GroupPasswordManager\Console;

use Pcsg\GroupPasswordManager\Constants\Tables;
use QUI;
use Symfony\Component\Console\Helper\Table;

/**
 * Utilities for password manager maintenance
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Utils extends QUI\System\Console\Tool
{
    protected $utils = array(
        'deletepasswords' => 'Löscht ein Passwort und alle Referenzen aus der Datenbank'
    );

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->setName('pcsg:gpm-utils')
            ->setDescription(
                'Passwort-Manager Utils - nur für Entwickler (!)'
            );
    }

    /**
     * Execute the console tool
     */
    public function execute()
    {
        QUI\Permissions\Permission::isSU();

        $this->writeLn('Utils-Liste:');
        $this->writeLn("\n");

        foreach ($this->utils as $patch => $desc) {
            $this->writeLn($patch . ' :: ' . $desc);
        }

        $this->writeLn("\n");

        $this->writeLn('Util: ');

        $patch = false;

        while ($patch === false) {
            $_patch = $this->readInput();

            if (!isset($this->utils[$_patch])) {
                $this->writeLn('Util existiert nicht.');
                $this->writeLn('Util: ');
                continue;
            }

            $patch = $_patch;
        }

        switch ($patch) {
            case 'deletepasswords':
                $this->writeLn("Password-IDs (kommasepariert):");
                $pwIds = $this->readInput();
                $pwIds = explode(',', $pwIds);

                $this->deletePasswords($pwIds);
                break;

            default:
                $this->exitFail('Keine Methode für Util gefunden.');
        }

        $this->exitSuccess();
    }

    /**
     * Löscht Passwörter aus der Datenbank (ohne Beachtung von Passwort-Rechten)
     *
     * @param array $ids
     */
    protected function deletePasswords($ids)
    {
        $DB = QUI::getDataBase();

        foreach ($ids as $id) {
            try {
                // pcsg_gpm_group_data_access
                $DB->delete(
                    QUI::getDBTableName(Tables::GROUP_TO_PASSWORDS),
                    array(
                        'dataId' => $id
                    )
                );

                // pcsg_gpm_password_data
                $DB->delete(
                    QUI::getDBTableName(Tables::PASSWORDS),
                    array(
                        'id' => $id
                    )
                );

                // pcsg_gpm_user_data_access
                $DB->delete(
                    QUI::getDBTableName(Tables::USER_TO_PASSWORDS),
                    array(
                        'dataId' => $id
                    )
                );

                // pcsg_gpm_user_data_access_meta
                $DB->delete(
                    QUI::getDBTableName(Tables::USER_TO_PASSWORDS_META),
                    array(
                        'dataId' => $id
                    )
                );
            } catch (\Exception $Exception) {
                $this->writeLn(
                    'Konnte Passwort #' . $id . ' nicht löschen: ' . $Exception->getMessage(),
                    'red'
                );
            }
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
