<?php

use Pcsg\GroupPasswordManager\Security\Utils;

/**
 * Generate a random password
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_passwords_generateRandom',
    function () {
        return Utils::generatePassword();
    },
    array(),
    'Permission::checkAdminUser'
);
