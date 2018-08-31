<?php

use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\CryptoActors;

/**
 * Syncs an auth plugin with all passwords, so a user can access all (eligible) passwords with all
 * authentication plugins he is registered with
 *
 * @param integer $authPluginId - id of auth plugin
 * @return bool - success
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_syncAuthPlugin',
    function ($authPluginId) {
        $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
        $CryptoUser = CryptoActors::getCryptoUser();

        try {
            $passwordIds = $CryptoUser->getNonFullyAccessiblePasswordIds($AuthPlugin);

            foreach ($passwordIds as $passwordId) {
                $CryptoUser->reEncryptPasswordAccessKey($passwordId);
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'syncAuchPlugin error: '.$Exception->getMessage()
            );

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'error.authentication.sync.authplugin',
                    [
                        'authPluginId' => $authPluginId
                    ]
                )
            );

            return false;
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'sequry/core',
                'success.authentication.sync.authplugin',
                [
                    'authPluginId' => $authPluginId
                ]
            )
        );

        return true;
    },
    ['authPluginId'],
    'Permission::checkUser'
);
