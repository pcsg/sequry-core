<?php

use \Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

/**
 * Check if the current session user is eligible to user basic
 * password manager functionality
 *
 * @return bool
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_actors_canUsePasswordManager',
    function ()
    {
        return CryptoActors::getCryptoUser()->canUsePasswordManager();
    },
    array(),
    'Permission::checkAdminUser'
);
