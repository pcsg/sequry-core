<?php

use \Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Get path to a javascript control that enables authentication for a specific auth plugin
 *
 * @param integer $authPluginId - id of auth plugin
 * @return string - path to javascript control
 */
function package_pcsg_grouppasswordmanager_ajax_auth_getAuthenticationControl($authPluginId)
{
    $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
    return $AuthPlugin->getAuthenticationControl();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_getAuthenticationControl',
    array('authPluginId'),
    'Permission::checkAdminUser'
);
