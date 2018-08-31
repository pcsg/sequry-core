<?php

use Sequry\Core\Security\Handler\Passwords;
use \Sequry\Core\Security\Handler\CryptoActors;


/**
 * Check if a user can access a Password and if not what's missing for access
 *
 * @param int $passwordId - Password ID
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_getPasswordAccessInfo',
    function ($passwordId) {
        $Password = Passwords::get((int)$passwordId);
        return CryptoActors::getCryptoUser()->getPasswordAccessInfo($Password);
    },
    ['passwordId'],
    'Permission::checkUser'
);
