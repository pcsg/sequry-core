<?php

use Sequry\Core\Security\Handler\Authentication;
use QUI\Utils\Security\Orthos;

/**
 * Edit a security class
 *
 * @param integer $id - security class id
 * @param string $data - edit data
 * @return bool - success
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_editSecurityClass',
    function ($id, $data) {
        $SecurityClass = Authentication::getSecurityClass((int)$id);

        try {
            $SecurityClass->edit(
                Orthos::clearArray(json_decode($data, true))
            );
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.auth.editSecurityClass.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::writeRecursive(
                'AJAX :: package_sequry_core_ajax_auth_editSecurityClass'
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
                'message.ajax.auth.editSecurityClass.success',
                [
                    'securityClassId'    => $SecurityClass->getId(),
                    'securityClassTitle' => $SecurityClass->getAttribute('title')
                ]
            )
        );

        return true;
    },
    ['id', 'data'],
    'Permission::checkAdminUser'
);
