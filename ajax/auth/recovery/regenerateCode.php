<?php

use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\Recovery;
use Sequry\Core\Security\HiddenString;

/**
 * Re-generate a Recovery Code for an Authentication Plugin
 *
 * @param integer $authPluginId - ID of authentication plugin
 * @param string $authData - Authentication information for the given authentication plugin
 * @return array|false - recovery code data; false on error
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_recovery_regenerateCode',
    function ($authPluginId, $authData) {
        $authData = new HiddenString($authData);

        try {
            $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);

            // generate recovery code
            $recoveryCode = Recovery::createEntry($AuthPlugin, $authData);
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.auth.recovery.regenerateCode.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_auth_recovery_regenerateCode -> '
                .$Exception->getMessage()
            );

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.general.error'
                )
            );

            return false;
        }

        return $recoveryCode;
    },
    ['authPluginId', 'authData'],
    'Permission::checkUser'
);
