<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\Recovery;

/**
 * Get recovery code for specific authentication plugin (for current session user)
 *
 * @param integer $authPluginId - id of authentication plugin
 * @return false|int - id of recovery code or false on error
 */
function package_pcsg_grouppasswordmanager_ajax_auth_getRecoveryCodeId($authPluginId)
{
    $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
    return Recovery::getRecoveryCodeId($AuthPlugin);
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_getRecoveryCodeId',
    array('authPluginId'),
    'Permission::checkAdminUser'
);
