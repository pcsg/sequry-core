<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Get unencrypted information of a password object
 *
 * @param integer $passwordId - the id of the password object
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getInfo($passwordId)
{
    return Passwords::getInfo((int)$passwordId);
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_getInfo',
    array('passwordId'),
    'Permission::checkAdminUser'
);