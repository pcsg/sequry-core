<?php

use \Sequry\Core\Security\Handler\Authentication;

/**
 * Get path to a javascript control that enables changing of authentication information for a specific auth plugin
 *
 * @param integer $authPluginId - id of auth plugin
 * @return string - path to javascript control
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_getChangeAuthenticationControl',
    function ($authPluginId) {
        $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
        return $AuthPlugin->getChangeAuthenticationControl();
    },
    ['authPluginId'],
    'Permission::checkUser'
);
