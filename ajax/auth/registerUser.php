<?php

use \Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\Recovery;
use Sequry\Core\Security\HiddenString;

/**
 * Register current session user and create a keypair for an authentication plugin
 *
 * @param integer $authPluginId - ID of authentication plugin
 * @param string $registrationData - authentication data
 * @return false|array - recovery code data; false on error
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_registerUser',
    function ($authPluginId, $registrationData) {
        try {
            // register with auth plugin
            $AuthPlugin      = Authentication::getAuthPlugin($authPluginId);
            $authInformation = $AuthPlugin->registerUser(new HiddenString($registrationData));

            // generate recovery code
            $recoveryCodeData = Recovery::createEntry($AuthPlugin, $authInformation);
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'error.auth.registeruser',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'sequry/core',
                'success.auth.registeruser'
            )
        );

        return $recoveryCodeData;
    },
    ['authPluginId', 'registrationData'],
    'Permission::checkUser'
);
