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
function package_sequry_core_ajax_auth_registerUser($authPluginId, $registrationData)
{
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
                array(
                    'error' => $Exception->getMessage()
                )
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
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_auth_registerUser',
    array('authPluginId', 'registrationData'),
    'Permission::checkAdminUser'
);
