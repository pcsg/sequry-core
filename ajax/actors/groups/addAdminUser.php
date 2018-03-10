<?php

use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Security\Handler\Authentication;

/**
 * Add an admin user to a group
 *
 * @param integer $groupId - id of QUIQQER group
 * @param integer $userId - Id of the Admin user
 *
 * @return bool - success
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_groups_addAdminUser',
    function ($groupId, $userId) {
        try {
            $CryptoGroup = CryptoActors::getCryptoGroup((int)$groupId);
            $CryptoUser  = CryptoActors::getCryptoUser((int)$userId);

            $CryptoGroup->addAdminUser($CryptoUser);
        } catch (\Sequry\Core\Exception\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.actors.groups.addAdminUser.error',
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
                'message.ajax.actors.groups.addAdminUser.success'
            )
        );

        return true;
    },
    array('groupId', 'userId'),
    'Permission::checkAdminUser'
);
