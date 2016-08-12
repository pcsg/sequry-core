<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

/**
 * Get security class id of password
 *
 * @param integer $passwordId - the id of the password object
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getAvailableAuthPluginIds($passwordId)
{
    $passwordId             = (int)$passwordId;
    $availableAuthPluginIds = array();
    $SecurityClass          = Passwords::getSecurityClass($passwordId);
    $authPlugins            = $SecurityClass->getAuthPlugins();
    $CryptoUser             = CryptoActors::getCryptoUser();

    /** @var \Pcsg\GroupPasswordManager\Security\Authentication\Plugin $AuthPlugin */
    foreach ($authPlugins as $AuthPlugin) {
        $unavaileblePasswordIds = $CryptoUser->getNonFullyAccessiblePasswordIds($AuthPlugin);

        if (!in_array($passwordId, $unavaileblePasswordIds)) {
            $availableAuthPluginIds[] = $AuthPlugin->getId();
        }
    }

    return $availableAuthPluginIds;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_getAvailableAuthPluginIds',
    array('passwordId'),
    'Permission::checkAdminUser'
);
