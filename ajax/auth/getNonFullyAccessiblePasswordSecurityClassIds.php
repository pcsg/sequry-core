<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Returns IDs of security classes of passwords which keys are not protected by
 * all possible authentication plugins of current session user
 *
 * @param integer $authPluginId - id of auth plugin
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_auth_getNonFullyAccessiblePasswordSecurityClassIds($authPluginId)
{
    $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
    $CryptoUser = CryptoActors::getCryptoUser();

    $passwordIds      = $CryptoUser->getNonFullyAccessiblePasswordIds($AuthPlugin);
    $securityClassIds = array();

    foreach ($passwordIds as $passwordId) {
        $SecurityClass = Passwords::getSecurityClass($passwordId);
        $securityClassIds[$SecurityClass->getId()] = true;
    }

    return array_keys($securityClassIds);
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_getNonFullyAccessiblePasswordSecurityClassIds',
    array('authPluginId'),
    'Permission::checkAdminUser'
);