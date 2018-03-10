<?php

use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\Recovery;
use Sequry\Core\Exception\Exception;

/**
 * Send recovery token via mail
 *
 * @param integer $authPluginId - Authentication Plugin ID
 * @return bool - success
 *
 * @throws \Sequry\Core\Exception\Exception
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_recovery_sendToken',
    function ($authPluginId) {
        try {
            $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
            Recovery::sendRecoveryToken($AuthPlugin);
        } catch (Exception $Exception) {
            throw $Exception;
        } catch (\Exception $Exception) {
            QUI\System\Log::writeRecursive(
                'AJAX :: package_sequry_core_ajax_auth_recovery_sendToken'
            );

            QUI\System\Log::writeException($Exception);

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
                'message.ajax.auth.recovery.sendToken.token_sent'
            )
        );

        return true;
    },
    array('authPluginId'),
    'Permission::checkAdminUser'
);
