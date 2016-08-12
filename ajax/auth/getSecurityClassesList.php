<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Get all available security classes that are registered
 *
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_auth_getSecurityClassesList()
{
    return Authentication::getSecurityClassesList();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_getSecurityClassesList',
    array(),
    'Permission::checkAdminUser'
);
