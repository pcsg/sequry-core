<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use QUI\Utils\Security\Orthos;

/**
 * Create a new security class
 *
 * @param array $data - security class data
 * @return integer - id of new security class
 */
function package_pcsg_grouppasswordmanager_ajax_auth_createSecurityClass($data)
{
    return Authentication::createSecurityClass(
        Orthos::clearArray(
            json_decode($data, true)
        )
    );
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_createSecurityClass',
    array('data'),
    'Permission::checkAdminUser'
);
