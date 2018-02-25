<?php

use Sequry\Core\Security\Handler\Authentication;

/**
 * Get a list of all installed authentication plugins
 *
 * @return array
 */
function package_sequry_core_ajax_auth_getAuthPluginList()
{
    return Authentication::getAuthPluginList();
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_auth_getAuthPluginList',
    array(),
    'Permission::checkAdminUser'
);
