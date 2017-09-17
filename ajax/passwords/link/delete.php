<?php

use Pcsg\GroupPasswordManager\Security\Handler\PasswordLinks;

/**
 * Delete a PasswordLink
 *
 * @param integer $linkId - ID of PasswordLink
 * @return bool - success
 *
 * @throws QUI\Exception
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_passwords_link_delete',
    function ($linkId) {
        $linkId = (int)$linkId;

        try {
            $PasswordLink = PasswordLinks::get($linkId);
            $PasswordLink->delete();
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'message.passwords.link.delete.error',
                    array(
                        'error'  => $Exception->getMessage(),
                        'linkId' => $linkId
                    )
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_pcsg_grouppasswordmanager_ajax_passwords_link_delete'
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

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'message.passwords.link.delete.success',
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
