<?php

use Sequry\Core\Security\Handler\Passwords;

/**
 * Get share data from password object
 *
 * @param integer $passwordId - ID of password
 * @return array - password data
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_getShareData',
    function ($passwordId) {
        $passwordId = (int)$passwordId;
        // get password data
        return Passwords::get($passwordId)->getShareData();
    },
    ['passwordId'],
    'Permission::checkUser'
);
