<?php

use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Events;
use Sequry\Core\Security\Utils;

/**
 * Add group(s) to a user
 *
 * @param integer $userId - id of CryptoUser
 * @param array $groupIds - IDs of groups that shall be added to the user
 * @return bool - success
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_addGroupsToUser',
    function ($userId, $groupIds) {
        $groupIds = Utils::safeJsonDecode($groupIds);

        try {
            $CryptoUser = CryptoActors::getCryptoUser((int)$userId);

            foreach ($groupIds as $groupId) {
                $CryptoGroup = CryptoActors::getCryptoGroup((int)$groupId);
                $CryptoGroup->addUser($CryptoUser);
            }

            Events::$addGroupsToUserAuthentication = true;
            $CryptoUser->save();
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX: actors/addGroupsToUser error: ' . $Exception->getMessage()
            );

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'error.actors.addgroupstouser',
                    array(
                        'userId' => (int)$userId
                    )
                )
            );

            return false;
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'sequry/core',
                'success.actors.addgroupstouser',
                array(
                    'userId'   => $CryptoUser->getId(),
                    'userName' => $CryptoUser->getName()
                )
            )
        );

        return true;
    },
    array('userId', 'groupIds'),
    'Permission::checkAdminUser'
);
