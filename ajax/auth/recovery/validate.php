<?php

use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\Recovery;
use Sequry\Core\Exception\Exception;
use QUI\Utils\Security\Orthos;
use Sequry\Core\Security\HiddenString;

/**
 * Send recovery token via mail
 *
 * @param integer $authPluginId - Authentication Plugin ID
 * @param string $token - Recovery token
 * @param string $code - Recovery code
 * @return bool - success
 *
 * @throws \Sequry\Core\Exception\Exception
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_recovery_validate',
    function ($authPluginId, $token, $code) {
        $token = new HiddenString(Orthos::clear($token));
        $code  = new HiddenString(Orthos::clear($code));

        try {
            $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);

            // This method recovers the secret for the given authentication plugin
            // and saves it in the current user session
            Recovery::recoverEntry($AuthPlugin, $code, $token);
        } catch (Exception $Exception) {
            throw $Exception;
        } catch (\Exception $Exception) {
            QUI\System\Log::writeRecursive(
                'AJAX :: package_sequry_core_ajax_auth_recovery_validate'
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

        return true;
    },
    array('authPluginId', 'token', 'code'),
    'Permission::checkAdminUser'
);
