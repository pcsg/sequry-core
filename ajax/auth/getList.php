<?php

/**
 * Get a list of all installed authentication plugins
 *
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_auth_getList()
{
    return \Pcsg\GroupPasswordManager\Security\Handler\Authentication::getList();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_getList',
    array(),
    'Permission::checkAdminUser'
);