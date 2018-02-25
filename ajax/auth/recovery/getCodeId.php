<?php

use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\Recovery;

/**
 * Get recovery code ID
 *
 * @param integer $authPluginId - id of authentication plugin
 * @return false|int - id of recovery code or false on error
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_recovery_getCodeId',
    function ($authPluginId) {
        $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
        return Recovery::getRecoveryCodeId($AuthPlugin);
    },
    array('authPluginId'),
    'Permission::checkAdminUser'
);
