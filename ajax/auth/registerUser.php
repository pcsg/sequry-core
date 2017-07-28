<?php

use \Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\Recovery;
use Pcsg\GroupPasswordManager\Security\HiddenString;

/**
 * Register current session user and create a keypair for an authentication plugin
 *
 * @param integer $authPluginId - ID of authentication plugin
 * @param string $registrationData - authentication data
 * @return false|array - recovery code data; false on error
 */
function package_pcsg_grouppasswordmanager_ajax_auth_registerUser($authPluginId, $registrationData)
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
                'pcsg/grouppasswordmanager',
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
            'pcsg/grouppasswordmanager',
            'success.auth.registeruser'
        )
    );

    return $recoveryCodeData;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_registerUser',
    array('authPluginId', 'registrationData'),
    'Permission::checkAdminUser'
);
