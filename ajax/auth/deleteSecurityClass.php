<?php

use Sequry\Core\Security\Handler\Authentication;

/**
 * Create a new security class
 *
 * @param integer $id - security class id
 * @return bool - success
 */
function package_sequry_core_ajax_auth_deleteSecurityClass($id)
{
    $SecurityClass = Authentication::getSecurityClass((int)$id);

    try {
        $SecurityClass->delete();
    } catch (QUI\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'sequry/core',
                'message.ajax.auth.deleteSecurityClass.error',
                array(
                    'error' => $Exception->getMessage()
                )
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
            array(
                'securityClassId'    => $SecurityClass->getId(),
                'securityClassTitle' => $SecurityClass->getAttribute('title')
            )
        )
    );

    return true;
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_auth_deleteSecurityClass',
    array('id'),
    'Permission::checkAdminUser'
);
