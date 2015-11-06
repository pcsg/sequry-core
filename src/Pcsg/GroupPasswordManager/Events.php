<?php

/**
 * This file contains \QUI\Kapitalschutz\Events
 */

namespace Pcsg\GroupPasswordManager;

use QUI;
use Pcsg\GroupPasswordManager\Security as Security;

/**
 * Class Events
 *
 * @package pcsg/grouppasswordmanager
 * @author www.pcsg.de (Patrick Müller)
 */
class Events
{
    public static function onUserLogin($User)
    {
        if (!isset($_REQUEST['password'])) {
            return;
        }

        QUI::getSession()->set(
            CryptoUser::ATTRIBUTE_PWHASH,
            Security\Hash::create(
                $_REQUEST['password'],
                QUI::conf('globals', 'salt')
            )
        );
    }
}