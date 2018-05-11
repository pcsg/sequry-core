<?php

use Sequry\Core\Security\Handler\CryptoActors;
use QUI\Utils\Security\Orthos;
use Sequry\Core\Exception\Exception as SequryException;

/**
 * Add an admin user to a group
 *
 * @param integer $groupId - id of QUIQQER group
 * @param integer $userId - Id of the Admin user
 *
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_groups_addAdminUsers',
    function ($groupId, $userIds) {
        $userIds = Orthos::clearArray(json_decode($userIds, true));

        try {
            $CryptoGroup = CryptoActors::getCryptoGroup((int)$groupId);

            foreach ($userIds as $userId) {
                $CryptoUser  = CryptoActors::getCryptoUser((int)$userId);
                $CryptoGroup->addAdminUser($CryptoUser);
            }
        } catch (SequryException $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.actors.groups.addAdminUsers.error',
                    array(
                        'error' => $Exception->getMessage()
                    )
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'exception.events.add.users.to.group.info'
                )
            );

            return false;
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'sequry/core',
                'message.ajax.actors.groups.addAdminUsers.success'
            )
        );

        return true;
    },
    array('groupId', 'userIds'),
    'Permission::checkAdminUser'
);
