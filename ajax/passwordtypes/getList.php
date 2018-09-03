<?php

use Sequry\Core\PasswordTypes\Handler;

/**
 * Get available password types
 *
 * @return array - password types
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwordtypes_getList',
    function () {
        return Handler::getList();
    },
    [],
    'Permission::checkUser'
);
