<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use \Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;


/**
 * Check if a user can access a Password and if not what's missing for access
 *
 * @param int $passwordId - Password ID
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_actors_getPasswordAccessInfo',
    function ($passwordId)
    {
        $Password = Passwords::get((int)$passwordId);
        return CryptoActors::getCryptoUser()->getPasswordAccessInfo($Password);
    },
    array('passwordId'),
    'Permission::checkAdminUser'
);
