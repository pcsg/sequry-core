<?php

use QUI\Utils\Security\Orthos;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Edit a password object
 *
 * @param integer $passwordId - ID of password
 * @param array $passwordData - edited data of password
 * @param array $authData - authentication information
 * @return false|array - new pasword data; false if data could not be retrieved
 *
 * @throws QUI\Exception
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_edit($passwordId, $passwordData, $authData)
{
    $passwordId = (int)$passwordId;

    // authenticate
    Passwords::getSecurityClass(
        $passwordId
    )->authenticate(
        json_decode($authData, true) // @todo diese daten ggf. filtern
    );

    // edit password
    try {
        $Password = Passwords::get($passwordId);

        $Password->setData(
            Orthos::clearArray(json_decode($passwordData, true))
        );

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'success.password.edit',
                array(
                    'passwordId' => $passwordId
                )
            )
        );

        // if owner changed during this edit process, data cannot be retrieved
        try {
            return $Password->getData();
        } catch (\Exception $Exception) {
            return false;
        }
    } catch (\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'error.password.edit',
                array(
                    'reason'     => $Exception->getMessage(),
                    'passwordId' => $passwordId
                )
            )
        );
    }
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_edit',
    array('passwordId', 'passwordData', 'authData'),
    'Permission::checkAdminUser'
);
