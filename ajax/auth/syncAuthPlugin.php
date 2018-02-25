<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

/**
 * Syncs an auth plugin with all passwords, so a user can access all (eligible) passwords with all
 * authentication plugins he is registered with
 *
 * @param integer $authPluginId - id of auth plugin
 * @return bool - success
 */
function package_pcsg_grouppasswordmanager_ajax_auth_syncAuthPlugin($authPluginId)
{
    $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
    $CryptoUser = CryptoActors::getCryptoUser();

    try {
        $passwordIds = $CryptoUser->getNonFullyAccessiblePasswordIds($AuthPlugin);

        foreach ($passwordIds as $passwordId) {
            $CryptoUser->reEncryptPasswordAccessKey($passwordId);
        }
    } catch (\Exception $Exception) {
        QUI\System\Log::addError(
            'syncAuchPlugin error: ' . $Exception->getMessage()
        );

        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'error.authentication.sync.authplugin',
                array(
                    'authPluginId' => $authPluginId
                )
            )
        );

        return false;
    }

    QUI::getMessagesHandler()->addSuccess(
        QUI::getLocale()->get(
            'pcsg/grouppasswordmanager',
            'success.authentication.sync.authplugin',
            array(
                'authPluginId' => $authPluginId
            )
        )
    );

    return true;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_syncAuthPlugin',
    array('authPluginId'),
    'Permission::checkAdminUser'
);
