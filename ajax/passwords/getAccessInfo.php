<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Get access info of password for session user
 *
 * @param integer $passwordId - ID of password
 * @return array - password data
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getAccessInfo($passwordId)
{
    return Passwords::get((int)$passwordId)->getAccessInfo();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_getAccessInfo',
    array('passwordId'),
    'Permission::checkAdminUser'
);
