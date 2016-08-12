<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Get share data from password object
 *
 * @param integer $passwordId - ID of password
 * @param array $authData - authentication information
 * @return array - password data
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getShareData($passwordId, $authData)
{
    $passwordId = (int)$passwordId;

    // authenticate
    Passwords::getSecurityClass(
        $passwordId
    )->authenticate(
        json_decode($authData, true) // @todo diese daten ggf. filtern
    );

    // get password data
    return Passwords::get($passwordId)->getShareData();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_getShareData',
    array('passwordId', 'authData'),
    'Permission::checkAdminUser'
);
