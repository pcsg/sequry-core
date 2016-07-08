<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use QUI\Utils\Security\Orthos;

/**
 * Register current session user and create a keypair for an authentication plugin
 *
 * @param integer $authPluginId - ID of authentication plugin
 * @param string $oldAuthInfo - old authentication information
 * @param string $newAuthInfo - new authentication information
 * @return bool
 */
function package_pcsg_grouppasswordmanager_ajax_auth_changeAuthenticationInformation(
    $authPluginId,
    $oldAuthInfo,
    $newAuthInfo
)
{
    $oldAuthInfo = Orthos::clear($oldAuthInfo);
    $newAuthInfo = Orthos::clear($newAuthInfo);

    try {
        $AuthPlugin = Authentication::getAuthPlugin($authPluginId);
        $AuthPlugin->changeAuthenticationInformation($oldAuthInfo, $newAuthInfo);
    } catch (QUI\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'error.auth.changeauth', array(
                    'error' => $Exception->getMessage()
                )
            )
        );

        return false;
    }

    QUI::getMessagesHandler()->addSuccess(
        QUI::getLocale()->get(
            'pcsg/grouppasswordmanager',
            'success.auth.changeauth'
        )
    );

    return true;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_changeAuthenticationInformation',
    array('authPluginId', 'oldAuthInfo', 'newAuthInfo'),
    'Permission::checkAdminUser'
);