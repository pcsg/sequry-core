<?php

use Sequry\Core\Security\Handler\PasswordLinks;

/**
 * Deactivate a PasswordLink
 *
 * @param integer $linkId - ID of PasswordLink
 * @return bool - success
 *
 * @throws QUI\Exception
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_link_deactivate',
    function ($linkId) {
        $linkId = (int)$linkId;

        try {
            $PasswordLink = PasswordLinks::get($linkId);
            $PasswordLink->deactivate();
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.passwords.link.deactivate.error',
                    array(
                        'error'  => $Exception->getMessage(),
                        'linkId' => $linkId
                    )
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_passwords_link_deactivate'
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
                'message.passwords.link.deactivate.success',
                array(
                    'linkId' => $linkId
                )
            )
        );

        return true;
    },
    array('linkId'),
    'Permission::checkAdminUser'
);
