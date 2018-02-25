<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\Recovery;
use Pcsg\GroupPasswordManager\Security\HiddenString;

/**
 * Change authentication data via recovered secret
 *
 * @param integer $authPluginId - ID of authentication plugin
 * @param string $newAuthData - new authentication information
 * @return array|false - recovery code data; false on error
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_auth_recovery_changeAuthData',
    function (
        $authPluginId,
        $newAuthData
    ) {
        $newAuthData = new HiddenString($newAuthData);

        try {
            $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);

            $AuthPlugin->changeAuthenticationInformation(
                Recovery::getRecoverySecret($AuthPlugin),
                $newAuthData
            );

            // generate recovery code
            $recoveryCode = Recovery::createEntry($AuthPlugin, $newAuthData);
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
                'AJAX :: package_pcsg_grouppasswordmanager_ajax_auth_recovery_changeAuthData -> '
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
    },
    array('authPluginId', 'newAuthData'),
    'Permission::checkAdminUser'
);
