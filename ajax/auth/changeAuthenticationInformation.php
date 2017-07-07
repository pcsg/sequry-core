<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\Recovery;

/**
 * Register current session user and create a keypair for an authentication plugin
 *
 * @param integer $authPluginId - ID of authentication plugin
 * @param string $oldAuthInfo - old authentication information
 * @param string $newAuthInfo - new authentication information
 * @param bool $recovery (optional) - oldAuthInfo is recovery code
 * @return array|false - recovery code data; false on error
 */
function package_pcsg_grouppasswordmanager_ajax_auth_changeAuthenticationInformation(
    $authPluginId,
    $oldAuthInfo,
    $newAuthInfo,
    $recovery = false
) {
//    $oldAuthInfo = Orthos::clear($oldAuthInfo);
//    $newAuthInfo = Orthos::clear($newAuthInfo);

    try {
        $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);

        if ($recovery) {
            $oldAuthInfo = Recovery::recoverEntry($AuthPlugin, $oldAuthInfo);
        }

        $AuthPlugin->changeAuthenticationInformation($oldAuthInfo, $newAuthInfo);

        // generate recovery code
        $recoveryCode = Recovery::createEntry($AuthPlugin, $newAuthInfo);
    } catch (QUI\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'error.auth.changeauth',
                array(
                    'error' => $Exception->getMessage()
                )
            )
        );

        return false;
    } catch (\Exception $Exception) {
        QUI\System\Log::addError(
            'AJAX :: package_pcsg_grouppasswordmanager_ajax_auth_changeAuthenticationInformation -> '
            . $Exception->getMessage()
        );

        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'message.general.error'
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

    return $recoveryCode;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_changeAuthenticationInformation',
    array('authPluginId', 'oldAuthInfo', 'newAuthInfo', 'recovery'),
    'Permission::checkAdminUser'
);
