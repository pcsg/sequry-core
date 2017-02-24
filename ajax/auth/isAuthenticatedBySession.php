<?php

use \Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Checks if the current session user has already authenticated
 * himself with a security class
 *
 * @param integer $securityClassId - id of security class
 * @return bool
 */
function package_pcsg_grouppasswordmanager_ajax_auth_isAuthenticatedBySession($securityClassId)
{
    return Authentication::getSecurityClass($securityClassId)->isAuthenticatedBySession();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_isAuthenticatedBySession',
    array('securityClassId'),
    'Permission::checkAdminUser'
);
