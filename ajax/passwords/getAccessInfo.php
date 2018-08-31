<?php

use Sequry\Core\Security\Handler\Passwords;

/**
 * Get access info of password for session user
 *
 * @param integer $passwordId - ID of password
 * @return array - password data
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_getAccessInfo',
    function ($passwordId) {
        return Passwords::get((int)$passwordId)->getAccessInfo();
    },
    ['passwordId'],
    'Permission::checkUser'
);
