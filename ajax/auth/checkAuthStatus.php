<?php

use \Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Checks the auth status for every authentication plugin necessary
 * to authenticate for a security class
 *
 * @param integer $securityClassId - id of security class
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_auth_checkAuthStatus',
    function ($securityClassId)
    {
        return Authentication::getSecurityClass((int)$securityClassId)->getAuthStatus();
    },
    array('securityClassId'),
    'Permission::checkAdminUser'
);
