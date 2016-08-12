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
    ini_set('display_errors', 1);

    // @todo clearArray könnte zuviel kaputtmachen, evtl. eigene clear methode schreiben
    $passwordData = \QUI\Utils\Security\Orthos::clearArray(
        json_decode($passwordData, true)
    );

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
                'error.password.create', array(
                    'error' => $Exception->getMessage()
                )
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
