<?php

use Sequry\Core\Security\Handler\Passwords;

/**
 * Create a new password object
 *
 * @param string $passwordData - password data
 * @return false|integer - false on error, Password ID on success
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_create',
    function ($passwordData) {
        $passwordData = json_decode($passwordData, true);

        // create password
        try {
            $newPasswordId = Passwords::createPassword($passwordData);
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'error.password.create',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_passwords_create -> '
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
                'success.password.create'
            )
        );

        return $newPasswordId;
    },
    ['passwordData'],
    'Permission::checkUser'
);
