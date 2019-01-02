<?php

/**
 * Get a list of all passwords the currently logged in user has access to
 *
 * @param string $searchParams - search options [json]
 * @return array|false - false on error or password list otherwise
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_getList',
    function ($searchParams) {
        try {
            $CryptoUser = \Sequry\Core\Security\Handler\CryptoActors::getCryptoUser();

            $searchParams = \QUI\Utils\Security\Orthos::clearArray(
                json_decode($searchParams, true)
            );

            $Grid      = new \QUI\Utils\Grid($searchParams);
            $passwords = $CryptoUser->getPasswordList($searchParams);

            return $Grid->parseResult(
                $passwords,
                $CryptoUser->getPasswordList($searchParams, true)
            );
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.passwords.getList.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.general.error'
                )
            );

            return false;
        }
    },
    ['searchParams'],
    'Permission::checkUser'
);
