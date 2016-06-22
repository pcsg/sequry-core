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
     * Return list of all security classes with name and description
     *
     * @param QUI\Users\User $User (optional) - if omitted use session user
     * @return CryptoUser
     */
    public static function getCryptoUser($User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        $userId = $User->getId();

        if (isset(self::$users[$userId])) {
            return self::$users[$userId];
        }

        self::$users[$userId] = new CryptoUser($userId);

        return self::$users[$userId];
    }
}