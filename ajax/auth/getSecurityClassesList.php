<?php

use Sequry\Core\Security\Handler\Authentication;

/**
 * Get all available security classes that are registered
 *
 * @return array
 */
function package_sequry_core_ajax_auth_getSecurityClassesList()
{
    return Authentication::getSecurityClassesList();
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_auth_getSecurityClassesList',
    array(),
    'Permission::checkAdminUser'
);
