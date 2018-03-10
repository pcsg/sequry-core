<?php

use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Security\Handler\Authentication;

/**
 * Remove an admin user from a group
 *
 * @param integer $groupId - id of QUIQQER group
 * @param integer $userId - Id of the Admin user
 *
 * @return bool - success
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_groups_removeAdminUser',
    function ($groupId, $userId) {
        try {
            $CryptoGroup = CryptoActors::getCryptoGroup((int)$groupId);
            $CryptoUser  = CryptoActors::getCryptoUser((int)$userId);

            $CryptoGroup->removeAdminUser($CryptoUser);
        } catch (\Sequry\Core\Exception\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.actors.groups.removeAdminUser.error',
                    array(
                        'error' => $Exception->getMessage()
                    )
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

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'sequry/core',
                'message.ajax.actors.groups.removeAdminUser.success'
            )
        );

        return true;
    },
    array('groupId', 'userId'),
    'Permission::checkAdminUser'
);
