<?php

use \Sequry\Core\Security\Handler\Authentication;

/**
 * Get path to a javascript control that enables registration for a specific auth plugin
 *
 * @param integer $authPluginId - id of auth plugin
 * @return string - path to javascript control
 */
function package_sequry_core_ajax_auth_getRegistrationControl($authPluginId)
{
    $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
    return $AuthPlugin->getRegistrationControl();
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_auth_getRegistrationControl',
    array('authPluginId'),
    'Permission::checkAdminUser'
);
