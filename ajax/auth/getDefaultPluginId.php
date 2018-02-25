<?php

use Sequry\Core\Security\Handler\Authentication;

/**
 * Get ID of the default authentication plugin (QUIQQER Password auth)
 *
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_getDefaultPluginId',
    function() {
        return Authentication::getDefaultAuthPluginId();
    },
    array(),
    'Permission::checkAdminUser'
);
