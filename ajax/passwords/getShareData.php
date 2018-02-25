<?php

use Sequry\Core\Security\Handler\Passwords;

/**
 * Get share data from password object
 *
 * @param integer $passwordId - ID of password
 * @return array - password data
 */
function package_sequry_core_ajax_passwords_getShareData($passwordId)
{
    $passwordId = (int)$passwordId;
    // get password data
    return Passwords::get($passwordId)->getShareData();
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_passwords_getShareData',
    array('passwordId'),
    'Permission::checkAdminUser'
);
