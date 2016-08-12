<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Get security class id of password
 *
 * @param integer $passwordId - the id of the password object
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getSecurityClassId($passwordId)
{
    return Passwords::getSecurityClass((int)$passwordId)->getId();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_getSecurityClassId',
    array('passwordId'),
    'Permission::checkAdminUser'
);
