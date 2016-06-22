<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Get a list of all installed authentication plugins
 *
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_auth_getAuthPluginList()
{
    return Authentication::getAuthPluginList();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_getAuthPluginList',
    array(),
    'Permission::checkAdminUser'
);