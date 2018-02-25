<?php

namespace Sequry\Core;

use Sequry\Core\Security\Handler\PasswordLinks;
use QUI;
use Sequry\Core\Constants\Tables;

/**
 * Class Cron
 *
 * General cron class for sequry/core
 */
class Cron
{
    /**
     * Deactivate all expired PasswordLinks
     *
     * @return void
     */
    public static function deactivateExpiredPasswordLinks()
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => Tables::passwordLink(),
            'where'  => array(
                'active' => 1
            )
        ));

        foreach ($result as $row) {
            // date validation and automatic deactivation is done in __construct of PasswordLink class
            PasswordLinks::get($row['id']);
        }
    }
}
