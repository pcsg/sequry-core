<?php

use Sequry\Core\Security\Handler\Authentication;

/**
 * Get a list of all installed authentication plugins
 *
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_getAuthPluginList',
    function () {
        return Authentication::getAuthPluginList();
    },
    [],
    'Permission::checkUser'
);
