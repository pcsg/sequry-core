<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\Recovery;

/**
 * Get recovery code is for specific authentication plugin (for current session user)
 *
 * @param integer $authPluginId - id of authentication plugin
 * @return array - id, title and description
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
