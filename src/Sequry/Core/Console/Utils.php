<?php

namespace Sequry\Core\Console;

use Sequry\Core\Constants\Tables;
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
        'checkownermismatch' => 'Prüft, bei welchen Passwörtern der Owner in der PW-Tabelle nicht mit dem tatsächlichen Owner übereinstimmt',
        'deletepasswords'    => 'Löscht ein Passwort und alle Referenzen aus der Datenbank'
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

            case 'checkownermismatch':
                $this->checkOwnerMismatch();
                break;

            default:
                $this->exitFail('Keine Methode für Util gefunden.');
        }

        $this->exitSuccess();
    }

    /**
     * Prüft, welche Passwörter einen falschen Owner in der Passwort-Tabelle haben
     *
     * @return void
     */
    protected function checkOwnerMismatch()
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id',
                'title',
                'ownerId',
                'ownerType'
            ),
            'from'   => 'pcsg_gpm_password_data'
        ));

        foreach ($result as $row) {
            switch ($row['ownerType']) {
                case '1':
                    $check = QUI::getDataBase()->fetch(array(
                        'select' => array(),
                        'from'   => 'pcsg_gpm_user_data_access',
                        'where'  => array(
                            'userId' => $row['ownerId'],
                            'dataId' => $row['id']
                        )
                    ));
                    break;

                case '2':
                    $check = QUI::getDataBase()->fetch(array(
                        'select' => array(),
                        'from'   => 'pcsg_gpm_group_data_access',
                        'where'  => array(
                            'groupId' => $row['ownerId'],
                            'dataId'  => $row['id']
                        )
                    ));
                    break;
            }

            if (empty($check)) {
                $this->writeLn("MISMATCH: Passwort #" . $row['id'] . " - " . $row['title']);
            }
        }
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
                    Tables::groupsToPasswords(),
                    array(
                        'dataId' => $id
                    )
                );

                // pcsg_gpm_password_data
                $DB->delete(
                    Tables::passwords(),
                    array(
                        'id' => $id
                    )
                );

                // pcsg_gpm_user_data_access
                $DB->delete(
                    Tables::usersToPasswords(),
                    array(
                        'dataId' => $id
                    )
                );

                // pcsg_gpm_user_data_access_meta
                $DB->delete(
                    Tables::usersToPasswordMeta(),
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
