<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Password
 */

namespace Pcsg\GroupPasswordManager\Security\Handler;

use Pcsg\GroupPasswordManager\CryptoUser;
use QUI;
use Pcsg\GroupPasswordManager\Constants\Tables;

/**
 * Class for for managing system actors - users and groups
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class CryptoActors
{
    /**
     * Crypto users
     *
     * @var array
     */
    protected static $users = array();

    /**
     * Crypto groups
     *
     * @var array
     */
    protected static $groups = array();

    /**
     * @todo ggf. auch andere Nutzer als den Session-Nutzer holen
     *
     * Return list of all security classes with name and description
     *
     * @return CryptoUser
     */
    public static function getCryptoUser()
    {
        $User   = QUI::getUserBySession();
        $userId = $User->getId();

        if (isset(self::$users[$userId])) {
            return self::$users[$userId];
        }

        self::$users[$userId] = new CryptoUser($userId);

        return self::$users[$userId];
    }
}