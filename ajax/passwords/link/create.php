<?php

use Sequry\Core\Security\Handler\PasswordLinks;

/**
 * Create a new PasswordLink
 *
 * @param integer $passwordId - ID of password
 * @param array $linkData - settings for PasswordLink
 * @return bool - success
 *
 * @throws QUI\Exception
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_link_create',
    function ($passwordId, $linkData) {
        $passwordId = (int)$passwordId;

        // create password link
        try {
            PasswordLinks::create(
                $passwordId,
                json_decode($linkData, true)
            );
        } catch (QUI\Exception $Exception) {

            QUI\System\Log::writeException($Exception);

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.passwords.link.create.error',
                    array(
                        'error'      => $Exception->getMessage(),
                        'passwordId' => $passwordId
                    )
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_passwords_link_create'
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
                'message.passwords.link.create.success',
                array(
                    'passwordId' => $passwordId
                )
            )
        );

        return true;
    },
    array('passwordId', 'linkData'),
    'Permission::checkAdminUser'
);
