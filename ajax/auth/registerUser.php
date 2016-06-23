<?php

use \Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Register current session user and create a keypair for an authentication plugin
 *
 * @param integer $authPluginId - ID of authentication plugin
 * @param array $authData - authentication data
 * @return bool
 */
function package_pcsg_grouppasswordmanager_ajax_auth_registerUser($authPluginId, $authData)
{
    try {
        $AuthPlugin = Authentication::getAuthPlugin($authPluginId);
        $AuthPlugin->registerUser($authData);
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

    return true;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_registerUser',
    array('authPluginId', 'authData'),
    'Permission::checkAdminUser'
);