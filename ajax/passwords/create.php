<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Create a new password object
 *
 * @param array $passwordData - password data
 * @return false|integer - false on error, Password ID on success
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_create($passwordData)
{
    $passwordData = json_decode($passwordData, true);

    if (!isset($passwordData['securityClassId'])
        || empty($passwordData['securityClassId'])) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'error.password.create.no.security.class'
            )
        );

        return false;
    }

    // create password
    try {
        $newPasswordId = Passwords::createPassword($passwordData);
    } catch (QUI\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'error.password.create',
                array(
                    'error' => $Exception->getMessage()
                )
            )
        );

        return false;
    } catch (\Exception $Exception) {
        QUI\System\Log::addError(
            'AJAX :: package_pcsg_grouppasswordmanager_ajax_passwords_create -> '
            . $Exception->getMessage()
        );

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
            'success.password.create'
        )
    );

    return $newPasswordId;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_create',
    array('passwordData', 'authData'),
    'Permission::checkAdminUser'
);
