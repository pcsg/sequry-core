<?php

use Pcsg\GroupPasswordManager\PasswordTypes\Handler;

/**
 * Get available password types
 *
 * @return array - password types
 */
function package_pcsg_grouppasswordmanager_ajax_passwordtypes_getList()
{
    return Handler::getList();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwordtypes_getList',
    array(),
    'Permission::checkAdminUser'
);
