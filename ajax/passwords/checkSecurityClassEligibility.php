<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Get edit data from password object
 *
 * @param integer $passwordId - ID of password
 * @param integer $securityClassId - ID of (potential) new security class
 * @param array $authData - authentication information
 * @return array - password data
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getSecurityClassEligibilityInfo(
    $passwordId,
    $securityClassId,
    $authData
) {
    $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);
    $Password      = Passwords::get((int)$passwordId);


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
    'package_pcsg_grouppasswordmanager_ajax_passwords_getSecurityClassEligibilityInfo',
    array('passwordId', 'authData'),
    'Permission::checkAdminUser'
);