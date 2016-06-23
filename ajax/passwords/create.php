<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Create a new password object
 *
 * @param array $passwordData - password data
 * @param array $authData - authentication information
 * @return false|integer - false on error, Password ID on success
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_create($passwordData, $authData)
{
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

    // authenticate
    Authentication::getSecurityClass(
        (int)$passwordData['securityClassId']
    )->authenticate(
        json_decode($authData, true) // @todo diese daten ggf. filtern
    );

    // create password
    return Passwords::createPassword($passwordData);
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_create',
    array('passwordData', 'authData'),
    'Permission::checkAdminUser'
);