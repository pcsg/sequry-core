<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

/**
 * Get info for auth plugins the user can use to authenticate for a specific password
 *
 * @param integer $passwordId - the id of the password
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getAvailableAuthPluginsInfo($passwordId)
{
    $passwordId              = (int)$passwordId;
    $availableAuthPluginInfo = array();
    $SecurityClass           = Passwords::getSecurityClass($passwordId);
    $authPlugins             = $SecurityClass->getAuthPlugins();
    $CryptoUser              = CryptoActors::getCryptoUser();

    /** @var \Pcsg\GroupPasswordManager\Security\Authentication\Plugin $AuthPlugin */
    foreach ($authPlugins as $AuthPlugin) {
        $unavailablePasswordIds = $CryptoUser->getNonFullyAccessiblePasswordIds($AuthPlugin);

        if (in_array($passwordId, $unavailablePasswordIds)) {
            $availableAuthPluginInfo[$AuthPlugin->getId()] = false;
            continue;
        }

        $availableAuthPluginInfo[$AuthPlugin->getId()] = true;
    }

    return $availableAuthPluginInfo;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_getAvailableAuthPluginsInfo',
    array('passwordId'),
    'Permission::checkAdminUser'
);
