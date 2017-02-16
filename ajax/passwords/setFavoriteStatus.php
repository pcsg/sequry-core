<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

/**
 * Set favorite status to a password
 *
 * @param integer $passwordId - ID of password
 * @param bool $status - true = favorite; false = unfavorite
 * @return bool - favorite status after editing
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_passwords_setFavoriteStatus',
    function ($passwordId, $status)
    {
        try {
            $CryptoUser = CryptoActors::getCryptoUser();
            $CryptoUser->setPasswordFavoriteStatus((int)$passwordId, $status);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_pcsg_grouppasswordmanager_ajax_passwords_setFavoriteStatus -> '
                . $Exception->getMessage()
            );

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'message.general.error'
                )
            );

            return !boolval($status);
        }

        return boolval($status);
    },
    array('passwordId', 'status'),
    'Permission::checkAdminUser'
);
