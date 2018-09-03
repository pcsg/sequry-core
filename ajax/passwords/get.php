<?php

use Sequry\Core\Security\Handler\Passwords;
use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Exception\InvalidAuthDataException;

/**
 * Get edit data from password object
 *
 * @param integer $passwordId - ID of password
 * @return array|false - password data or false on error
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_get',
    function ($passwordId) {
        $passwordId = (int)$passwordId;

        try {
            // get password data
            $Password = Passwords::get($passwordId);
            $data     = $Password->getData();

            $Password->increasePublicViewCount();

            // increase personal view count
            $CryptoUser = CryptoActors::getCryptoUser();
            $CryptoUser->increasePasswordViewCount($passwordId);

            return $data;
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.passwords.get.error',
                    [
                        'error'      => $Exception->getMessage(),
                        'passwordId' => $passwordId
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_passwords_get -> '.$Exception->getMessage()
            );

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.general.error'
                )
            );

            return false;
        }
    },
    ['passwordId'],
    'Permission::checkUser'
);
