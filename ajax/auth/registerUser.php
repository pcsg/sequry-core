<?php

use \Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\Recovery;

/**
 * Register current session user and create a keypair for an authentication plugin
 *
 * @param integer $authPluginId - ID of authentication plugin
 * @param array $registrationData - authentication data
 * @return string - recovery code
 */
function package_pcsg_grouppasswordmanager_ajax_auth_registerUser($authPluginId, $registrationData)
{
    try {
        // register with auth plugin
        $AuthPlugin      = Authentication::getAuthPlugin($authPluginId);
        $authInformation = $AuthPlugin->registerUser(
            json_decode($registrationData, true)
        );

        // generate recovery code
        $recoveryCode = Recovery::createEntry($AuthPlugin, $authInformation);
    } catch (QUI\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'error.auth.registeruser', array(
                    'error' => $Exception->getMessage()
                )
            )
        );

        return false;
    }

    QUI::getMessagesHandler()->addSuccess(
        QUI::getLocale()->get(
            'pcsg/grouppasswordmanager',
            'success.auth.registeruser'
        )
    );

    return $recoveryCode;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_registerUser',
    array('authPluginId', 'registrationData'),
    'Permission::checkAdminUser'
);