<?php

use \Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Checks if the current session user is already authenticated
 * for a security class
 *
 * @param integer $securityClassId - id of security class
 * @return bool
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_auth_isAuthenticated',
    function ($securityClassId) {
        return Authentication::getSecurityClass($securityClassId)->isAuthenticated();
    },
    array('securityClassId'),
    'Permission::checkAdminUser'
);
