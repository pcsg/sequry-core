<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Delete a password object
 *
 * @param integer $passwordId - ID of password
 * @param array $authData - authentication information
 * @return true - on success
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_delete($passwordId, $authData)
{
    $passwordId = (int)$passwordId;

    // authenticate
    Passwords::getSecurityClass(
        $passwordId
    )->authenticate(
        json_decode($authData, true) // @todo diese daten ggf. filtern
    );

    // delete password
    try {
        Passwords::get($passwordId)->delete();

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'success.password.delete', array(
                    'passwordId' => $passwordId
                )
            )
        );
    } catch (\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'error.password.delete', array(
                    'passwordId' => $passwordId,
                    'reason'     => $Exception->getMessage()
                )
            )
        );
    }

    return true;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_delete',
    array('passwordId', 'authData'),
    'Permission::checkAdminUser'
);
