<?php

use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\Recovery;
use Sequry\Core\Security\HiddenString;

/**
 * Change authentication data via recovered secret
 *
 * @param integer $authPluginId - ID of authentication plugin
 * @param string $newAuthData - new authentication information
 * @return array|false - recovery code data; false on error
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_recovery_changeAuthData',
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
                    'sequry/core',
                    'error.auth.changeauth',
                    array(
                        'error' => $Exception->getMessage()
                    )
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_auth_recovery_changeAuthData -> '
                . $Exception->getMessage()
            );

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.general.error'
                )
            );

            return false;
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'sequry/core',
                'success.auth.changeauth'
            )
        );

        return $recoveryCode;
    },
    array('authPluginId', 'newAuthData'),
    'Permission::checkAdminUser'
);
