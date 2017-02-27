<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Get a list of all installed authentication plugins
 *
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_auth_getDefaultPluginId',
    function() {
        return Authentication::getDefaultAuthPluginId();
    },
    array(),
    'Permission::checkAdminUser'
);
