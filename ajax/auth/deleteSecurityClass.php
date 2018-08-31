<?php

use Sequry\Core\Security\Handler\Authentication;

/**
 * Create a new security class
 *
 * @param integer $id - security class id
 * @return bool - success
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_deleteSecurityClass',
    function ($id) {
        $SecurityClass = Authentication::getSecurityClass((int)$id);

        try {
            $SecurityClass->delete();
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.auth.deleteSecurityClass.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::writeRecursive(
                'AJAX :: package_sequry_core_ajax_auth_deleteSecurityClass'
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
                'message.ajax.auth.deleteSecurityClass.success',
                [
                    'securityClassId'    => $SecurityClass->getId(),
                    'securityClassTitle' => $SecurityClass->getAttribute('title')
                ]
            )
        );

        return true;
    },
    ['id'],
    'Permission::checkAdminUser'
);
