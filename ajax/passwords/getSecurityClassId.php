<?php

use Sequry\Core\Security\Handler\Passwords;

/**
 * Get security class id of password
 *
 * @param integer $passwordId - the id of the password object
 * @return int
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_getSecurityClassId',
    function ($passwordId) {
        $securityClassIds = Passwords::getSecurityClassIds([$passwordId]);

        if (empty($securityClassIds)) {
            return false;
        }

        return current($securityClassIds);
    },
    ['passwordId'],
    'Permission::checkUser'
);
