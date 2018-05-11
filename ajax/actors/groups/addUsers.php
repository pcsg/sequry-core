<?php

use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Events;
use Sequry\Core\Exception\Exception as SequryException;

/**
 * Add user(s) to a group
 *
 * @param integer $groupId - id of crypto group
 * @param string $userIds - IDs of users that shall be added to the group
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_groups_addUsers',
    function ($groupId, $userIds) {
        $userIds           = json_decode($userIds, true);
        $groupId           = (int)$groupId;
        $successfullyAdded = [];

        Events::$addUsersToGroupAuthentication = true;

        try {
            $CryptoGroup = CryptoActors::getCryptoGroup($groupId);

            foreach ($userIds as $userId) {
                $CryptoUser = CryptoActors::getCryptoUser((int)$userId);
                $CryptoGroup->addUser($CryptoUser);
                $CryptoUser->save();

                if ($CryptoUser->isInGroup($groupId)) {
                    $successfullyAdded[] = $userId;
                }
            }
        } catch (SequryException $Exception) {
            throw $Exception;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX: actors/addUsersToGroup error: ' . $Exception->getMessage()
            );

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'error.actors.adduserstogroup',
                    array(
                        'groupId' => (int)$groupId
                    )
                )
            );

            return false;
        }

        if (!empty($successfullyAdded)) {
            QUI::getMessagesHandler()->addSuccess(
                QUI::getLocale()->get(
                    'sequry/core',
                    'success.actors.adduserstogroup',
                    array(
                        'groupId'   => $CryptoGroup->getId(),
                        'groupName' => $CryptoGroup->getName()
                    )
                )
            );
        }

        return true;
    },
    array('groupId', 'userIds'),
    'Permission::checkAdminUser'
);
