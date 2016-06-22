<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

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
    Authentication::authenticateWithSecurityClass(
        (int)$passwordData['securityClassId'],
        json_decode($authData, true) // @todo diese daten ggf. filtern
    );

    // @todo create password
    \QUI\System\Log::writeRecursive("create password");

    return 1;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_create',
    array('passwordData', 'authData'),
    'Permission::checkAdminUser'
);