<?php

use \Sequry\Core\Security\Handler\Authentication;

/**
 * Get path to a javascript control that enables registration for a specific auth plugin
 *
 * @param integer $authPluginId - id of auth plugin
 * @return string - path to javascript control
 * @throws \Sequry\Core\Exception\Exception
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_getRegistrationControl',
    function ($authPluginId) {
        $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
        return $AuthPlugin->getRegistrationControl();
    },
    ['authPluginId'],
    'Permission::checkUser'
);
