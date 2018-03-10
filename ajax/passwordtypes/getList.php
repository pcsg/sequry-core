<?php

use Sequry\Core\PasswordTypes\Handler;

/**
 * Get available password types
 *
 * @return array - password types
 */
function package_sequry_core_ajax_passwordtypes_getList()
{
    return Handler::getList();
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_passwordtypes_getList',
    array(),
    'Permission::checkAdminUser'
);
