<?php

use Sequry\Core\Security\Handler\Authentication;

/**
 * Get all available security classes that are registered
 *
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_getSecurityClassesList',
    function () {
        return Authentication::getSecurityClassesList();
    },
    [],
    'Permission::checkUser'
);
