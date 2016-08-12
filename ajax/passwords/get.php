<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Get edit data from password object
 *
 * @param integer $passwordId - ID of password
 * @param array $authData - authentication information
 * @return array - password data
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_get($passwordId, $authData)
{
    $passwordId = (int)$passwordId;

    // authenticate
    Passwords::getSecurityClass(
        $passwordId
    )->authenticate(
        json_decode($authData, true) // @todo diese daten ggf. filtern
    );

    // get password data
    return Passwords::get($passwordId)->getData();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_get',
    array('passwordId', 'authData'),
    'Permission::checkAdminUser'
);
