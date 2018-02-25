<?php

use Sequry\Core\Security\Handler\Passwords;
use Sequry\Core\Security\Handler\CryptoActors;

/**
 * Set favorite status to a password
 *
 * @param integer $passwordId - ID of password
 * @param bool $status - true = favorite; false = unfavorite
 * @return bool - favorite status after editing
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_setFavoriteStatus',
    function ($passwordId, $status)
    {
        try {
            $CryptoUser = CryptoActors::getCryptoUser();
            $CryptoUser->setPasswordFavoriteStatus((int)$passwordId, $status);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_passwords_setFavoriteStatus -> '
                . $Exception->getMessage()
            );

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
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
