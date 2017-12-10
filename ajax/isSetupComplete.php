<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Checks if the system is set up to use Sequry
 *
 * @return bool
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_isSetupComplete',
    function() {
        return Passwords::isSetupComplete();
    },
    array(),
    'Permission::checkAdminUser'
);
