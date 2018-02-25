<?php

use Sequry\Core\Security\Handler\Passwords;

/**
 * Get security class id of password
 *
 * @param integer $passwordId - the id of the password object
 * @return int
 */
function package_sequry_core_ajax_passwords_getSecurityClassId($passwordId)
{
    $securityClassIds = Passwords::getSecurityClassIds(array($passwordId));

    if (empty($securityClassIds)) {
        return false;
    }

    return current($securityClassIds);
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_passwords_getSecurityClassId',
    array('passwordId'),
    'Permission::checkAdminUser'
);
