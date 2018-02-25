<?php

use Sequry\Core\Security\Handler\Passwords;

/**
 * Checks if the system is set up to use Sequry
 *
 * @return bool
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_isSetupComplete',
    function() {
        return Passwords::isSetupComplete();
    },
    array(),
    'Permission::checkAdminUser'
);
