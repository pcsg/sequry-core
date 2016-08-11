<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Checks if authentication information for a specific security class is correct
 *
 * @param integer $securityClassId - security class ID
 * @param array $authData - authentication information
 * @return bool - true if correct, false if not correct
 */
function package_pcsg_grouppasswordmanager_ajax_auth_checkAuthInfo($securityClassId, $authData)
{
    try {
        $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);
        $SecurityClass->authenticate(
            json_decode($authData, true) // @todo ggf. filtern
        );
    } catch (\Exception $Exception) {
        return false;
    }

    return true;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_checkAuthInfo',
    array('securityClassId', 'authData'),
    'Permission::checkAdminUser'
);