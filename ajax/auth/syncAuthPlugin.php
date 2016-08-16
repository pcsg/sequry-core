<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Syncs an auth plugin with all passwords, so a user can access all (eligible) passwords with all
 * authentication plugins he is registered with
 *
 * @param integer $authPluginId - id of auth plugin
 * @param array $authData - authentication information for all relevant security classes
 * @return bool - success
 */
function package_pcsg_grouppasswordmanager_ajax_auth_syncAuthPlugin($authPluginId, $authData)
{
    $authData = json_decode($authData, true); // @todo ggf. filtern

    foreach ($authData as $securityClassId => $securityClassAuthData) {
        try {
            $SecurityClass = Authentication::getSecurityClass($securityClassId);
            $SecurityClass->authenticate($securityClassAuthData);
        } catch (\Exception $Exception) {
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
    }

    try {
        $AuthPlugin  = Authentication::getAuthPlugin((int)$authPluginId);
        $CryptoUser  = CryptoActors::getCryptoUser();
        $passwordIds = $CryptoUser->getNonFullyAccessiblePasswordIds($AuthPlugin);

        foreach ($passwordIds as $passwordId) {
            $CryptoUser->reEncryptAccessKey($passwordId);
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
    array('authPluginId', 'authData'),
    'Permission::checkAdminUser'
);
