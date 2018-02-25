<?php

use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\CryptoActors;

/**
 * Checks if a user has access to passwords which keys are not protected by all possible authentication plugins
 *
 * @param integer $authPluginId - id of auth plugin
 * @return bool
 */
function package_sequry_core_ajax_auth_hasNonFullyAccessiblePasswords($authPluginId)
{
    $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
    $CryptoUser = CryptoActors::getCryptoUser();

    return count($CryptoUser->getNonFullyAccessiblePasswordIds($AuthPlugin)) > 0;
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_auth_hasNonFullyAccessiblePasswords',
    array('authPluginId'),
    'Permission::checkAdminUser'
);
