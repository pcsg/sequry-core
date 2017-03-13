<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Checks if authentication information for a specific password is correct
 *
 * @param int $passwordId - password ID
 * @param array $authData - authentication information
 * @return bool - true if correct, false if not correct
 */
function package_pcsg_grouppasswordmanager_ajax_auth_checkAuthInfoPassword($passwordId, $authData)
{
    $SecurityClass = Passwords::getSecurityClass($passwordId);
    $authData      = json_decode($authData, true);

    // no session cache on check
    if (isset($authData['sessioncache'])) {
        unset($authData['sessioncache']);
    }

    $SecurityClass->authenticate($authData);

    return true;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_checkAuthInfoPassword',
    array('passwordId', 'authData'),
    'Permission::checkAdminUser'
);
