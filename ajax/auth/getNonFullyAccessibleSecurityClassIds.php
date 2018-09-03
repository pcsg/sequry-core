<?php

use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Security\Handler\Passwords;

/**
 * Returns IDs of security classes of passwords which keys are not protected by
 * all possible authentication plugins of current session user
 *
 * @param integer $authPluginId - id of auth plugin
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_getNonFullyAccessibleSecurityClassIds',
    function ($authPluginId) {
        $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
        $CryptoUser = CryptoActors::getCryptoUser();

        $passwordIds      = $CryptoUser->getNonFullyAccessiblePasswordIds($AuthPlugin);
        $securityClassIds = [];

        foreach ($passwordIds as $passwordId) {
            $SecurityClass                             = Passwords::getSecurityClass($passwordId);
            $securityClassIds[$SecurityClass->getId()] = true;
        }

        return array_keys($securityClassIds);
    },
    ['authPluginId'],
    'Permission::checkUser'
);
