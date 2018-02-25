<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Get share data from password object
 *
 * @param integer $passwordId - ID of password
 * @return array - password data
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getShareData($passwordId)
{
    $passwordId = (int)$passwordId;
    // get password data
    return Passwords::get($passwordId)->getShareData();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_getShareData',
    array('passwordId'),
    'Permission::checkAdminUser'
);
