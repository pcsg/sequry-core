<?php

use \Sequry\Core\Security\Handler\CryptoActors;

/**
 * Check if the current session user is eligible to user basic
 * password manager functionality
 *
 * @return bool
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_canUsePasswordManager',
    function () {
        return CryptoActors::getCryptoUser()->canUsePasswordManager();
    },
    [],
    'Permission::checkUser'
);
