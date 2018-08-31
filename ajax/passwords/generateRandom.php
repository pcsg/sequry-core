<?php

use Sequry\Core\Security\Utils;

/**
 * Generate a random password
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_generateRandom',
    function () {
        return Utils::generatePassword();
    },
    [],
    'Permission::checkUser'
);
