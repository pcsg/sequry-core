<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Get ID of the default authentication plugin (QUIQQER Password auth)
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
