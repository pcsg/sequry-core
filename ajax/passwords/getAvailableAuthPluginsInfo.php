<?php

use Sequry\Core\Security\Handler\Passwords;
use Sequry\Core\Security\Handler\CryptoActors;

/**
 * Get info for auth plugins the user can use to authenticate for a specific password
 *
 * @param integer $passwordId - the id of the password
 * @return array
 */
function package_sequry_core_ajax_passwords_getAvailableAuthPluginsInfo($passwordId)
{
    $passwordId              = (int)$passwordId;
    $availableAuthPluginInfo = array();
    $SecurityClass           = Passwords::getSecurityClass($passwordId);
    $authPlugins             = $SecurityClass->getAuthPlugins();
    $CryptoUser              = CryptoActors::getCryptoUser();

    /** @var \Sequry\Core\Security\Authentication\Plugin $AuthPlugin */
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
    'package_sequry_core_ajax_passwords_getAvailableAuthPluginsInfo',
    array('passwordId'),
    'Permission::checkAdminUser'
);
