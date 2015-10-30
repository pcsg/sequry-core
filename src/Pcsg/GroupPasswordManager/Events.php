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
    const VAR_PWHASH = 'pcsg_gpw_pwhash';

    public static function onUserLogin($User)
    {
        if (!isset($_REQUEST['password'])) {
            return;
        }

        QUI::getSession()->set(
            self::VAR_PWHASH,
            Security\Hash::createHash($_REQUEST['password'])
        );
    }
}