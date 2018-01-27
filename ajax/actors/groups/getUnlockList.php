<?php

use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use QUI\Utils\Security\Orthos;
use QUI\Utils\Grid;

/**
 * Get a list of all users that have to be unlocked by a group admin (session user)
 *
 * @param string $searchParams - search options [json]
 * @return array|false - false on error or groupadmin list otherwise
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_actors_groups_getUnlockList',
    function ($searchParams) {
        try {
            $CryptoUser   = CryptoActors::getCryptoUser();
            $searchParams = Orthos::clearArray(
                json_decode($searchParams, true)
            );

            $Grid = new Grid($searchParams);
            $list = $CryptoUser->getPasswordList($searchParams);

            return $Grid->parseResult(
                $list,
                $CryptoUser->getPasswordList($searchParams, true)
            );
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'message.ajax.actors.groups.getUnlockList.error',
                    array(
                        'error' => $Exception->getMessage()
                    )
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_pcsg_grouppasswordmanager_ajax_actors_groups_getUnlockList'
            );

            QUI\System\Log::writeException($Exception);

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'message.general.error'
                )
            );

            return false;
        }
    },
    array('searchParams'),
    'Permission::checkAdminUser'
);
