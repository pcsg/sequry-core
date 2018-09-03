<?php

use Sequry\Core\Security\Handler\CryptoActors;

/**
 * Re-encrypt all keys a user has access to
 *
 * @return false|integer - false on error, Password ID on success
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_reEncryptAll',
    function () {
        try {
            $CryptoUser = CryptoActors::getCryptoUser();
            $CryptoUser->reEncryptAllKeys();
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.reEncryptAll.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_actors_reEncryptAll -> '
                .$Exception->getMessage()
            );

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.general.error'
                )
            );

            return false;
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'sequry/core',
                'message.ajax.reEncryptAll.success'
            )
        );

        return true;
    },
    [],
    'Permission::checkUser'
);
