<?php

use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\Recovery;
use Sequry\Core\Security\HiddenString;

/**
 * Register current session user and create a keypair for an authentication plugin
 *
 * @param integer $authPluginId - ID of authentication plugin
 * @param string $oldAuthInfo - old authentication information
 * @param string $newAuthInfo - new authentication information
 * @return array|false - recovery code data; false on error
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_changeAuthenticationInformation',
    function (
        $authPluginId,
        $oldAuthInfo,
        $newAuthInfo
    ) {
        $oldAuthInfo = new HiddenString($oldAuthInfo);
        $newAuthInfo = new HiddenString($newAuthInfo);

        try {
            $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
            $AuthPlugin->changeAuthenticationInformation($oldAuthInfo, $newAuthInfo);

            // generate recovery code
            $recoveryCode = Recovery::createEntry($AuthPlugin, $newAuthInfo);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'error.auth.changeauth',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_auth_changeAuthenticationInformation -> '
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

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'sequry/core',
                'success.auth.changeauth'
            )
        );

        return $recoveryCode;
    },
    ['authPluginId', 'oldAuthInfo', 'newAuthInfo', 'recoveryToken'],
    'Permission::checkUser'
);
