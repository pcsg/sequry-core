<?php

use \Sequry\Core\Security\Handler\Authentication;

/**
 * Get path to a javascript control that enables authentication for a specific auth plugin
 *
 * @param integer $authPluginId - id of auth plugin
 * @return string - path to javascript control
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_getAuthenticationControl',
    function ($authPluginId) {
        $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
        return $AuthPlugin->getAuthenticationControl();
    },
    ['authPluginId'],
    'Permission::checkUser'
);
