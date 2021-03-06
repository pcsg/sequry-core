<?php

use Sequry\Core\PasswordTypes\Handler;
use Sequry\Core\Security\Handler\Passwords;
use QUI\Utils\Security\Orthos;
use Sequry\Core\Security\Handler\CryptoActors;

/**
 * Get a single password object
 *
 * @param integer $passwordId - the id of the password object
 * @return array|false - view data; false on error
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_getViewData',
    function ($passwordId) {
        $passwordId = (int)$passwordId;

        try {
            $Password = Passwords::get($passwordId);
            return $Password->getViewData();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.passwords.getView.error',
                    [
                        'error'      => $Exception->getMessage(),
                        'passwordId' => $passwordId
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_passwords_getView -> '.$Exception->getMessage()
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
    },
    ['passwordId'],
    'Permission::checkUser'
);
