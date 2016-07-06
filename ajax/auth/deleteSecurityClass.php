<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use QUI\Utils\Security\Orthos;

/**
 * Create a new security class
 *
 * @param integer $id - security class id
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_auth_deleteSecurityClass($id)
{
    $SecurityClass = Authentication::getSecurityClass((int)$id);
    return $SecurityClass->delete();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_deleteSecurityClass',
    array('id'),
    'Permission::checkAdminUser'
);