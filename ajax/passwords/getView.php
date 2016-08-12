<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Get a single password object
 *
 * @param integer $passwordId - the id of the password object
 * @param array $authData - authentication information
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getView($passwordId, $authData)
{
    ini_set('display_errors', 1);

    $passwordId = (int)$passwordId;

    // authenticate
    Passwords::getSecurityClass(
        $passwordId
    )->authenticate(
        json_decode($authData, true) // @todo diese daten ggf. filtern
    );

    return Passwords::get($passwordId)->getViewData();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_getView',
    array('passwordId', 'authData'),
    'Permission::checkAdminUser'
);
