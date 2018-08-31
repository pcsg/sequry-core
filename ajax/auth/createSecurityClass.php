<?php

use Sequry\Core\Security\Handler\Authentication;
use QUI\Utils\Security\Orthos;

/**
 * Create a new security class
 *
 * @param string $data - security class data
 * @return bool - success
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_createSecurityClass',
    function ($data)
    {
        try {
            $newSecurityClassId = Authentication::createSecurityClass(
                Orthos::clearArray(json_decode($data, true))
            );
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.auth.createSecurityClass.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::writeRecursive(
                'AJAX :: package_sequry_core_ajax_auth_createSecurityClass'
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

        $SecurityClass = Authentication::getSecurityClass($newSecurityClassId);

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'sequry/core',
                'message.ajax.auth.createSecurityClass.success',
                [
                    'securityClassId'    => $SecurityClass->getId(),
                    'securityClassTitle' => $SecurityClass->getAttribute('title')
                ]
            )
        );

        return true;
    },
    ['data'],
    'Permission::checkAdminUser'
);
