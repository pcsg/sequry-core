<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

/**
 * Get edit data from password object
 *
 * @param integer $passwordId - ID of password
 * @param array $authData - authentication information
 * @return array|false - password data or false on error
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_get($passwordId, $authData)
{
    $passwordId = (int)$passwordId;

    try {
        // authenticate
        Passwords::getSecurityClass(
            $passwordId
        )->authenticate(
            json_decode($authData, true) // @todo diese daten ggf. filtern
        );

        // get password data
        $Password = Passwords::get($passwordId);

        $Password->increasePublicViewCount();

        // increase personal view count
        $CryptoUser = CryptoActors::getCryptoUser();
        $CryptoUser->increasePasswordViewCount($passwordId);

        return $Password->getData();
    } catch (QUI\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'message.ajax.passwords.get.error',
                array(
                    'error'      => $Exception->getMessage(),
                    'passwordId' => $passwordId
                )
            )
        );

        return false;
    } catch (\Exception $Exception) {
        QUI\System\Log::addError(
            'AJAX :: package_pcsg_grouppasswordmanager_ajax_passwords_get -> ' . $Exception->getMessage()
        );

        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'message.general.error'
            )
        );

        return false;
    }
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_get',
    array('passwordId', 'authData'),
    'Permission::checkAdminUser'
);
