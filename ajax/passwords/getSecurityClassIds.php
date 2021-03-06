<?php

use Sequry\Core\Security\Handler\Passwords;
use QUI\Utils\Security\Orthos;

/**
 * Get security class IDs of multiple passwords
 *
 * @param integer $passwordId - the id of the password object
 * @return array - security class ids
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_getSecurityClassIds',
    function ($passwordIds) {
        $passwordIds = Orthos::clearArray(json_decode($passwordIds, true));
        return Passwords::getSecurityClassIds($passwordIds);
    },
    ['passwordIds'],
    'Permission::checkUser'
);
