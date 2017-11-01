<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\Recovery;
use Pcsg\GroupPasswordManager\Exception\Exception;

/**
 * Send recovery token via mail
 *
 * @param integer $authPluginId - Authentication Plugin ID
 * @return bool - success
 *
 * @throws \Pcsg\GroupPasswordManager\Exception\Exception
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_auth_recovery_sendToken',
    function ($authPluginId) {
        try {
            $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
            Recovery::sendRecoveryToken($AuthPlugin);
        } catch (Exception $Exception) {
            throw $Exception;
        } catch (\Exception $Exception) {
            QUI\System\Log::writeRecursive(
                'AJAX :: package_pcsg_grouppasswordmanager_ajax_auth_recovery_sendToken'
            );

            QUI\System\Log::writeException($Exception);

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'message.general.error'
                )
            );

            return false;
        }

        return true;
    },
    array('authPluginId'),
    'Permission::checkAdminUser'
);
