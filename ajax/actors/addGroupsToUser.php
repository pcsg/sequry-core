<?php

use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Events;

/**
 * Add group(s) to a user
 *
 * @param integer $userId - id of CryptoUser
 * @param array $groupIds - IDs of groups that shall be added to the user
 * @return bool - success
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_actors_addGroupsToUser',
    function ($userId, $groupIds) {
        $groupIds = json_decode($groupIds, true);

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
                    'pcsg/grouppasswordmanager',
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
                'pcsg/grouppasswordmanager',
                'success.actors.addgroupstouser',
                array(
                    'userId'   => $CryptoUser->getId(),
                    'userName' => $CryptoUser->getName()
                )
            )
        );

        return true;
    },
    array('userId', 'groupIds', 'authData'),
    'Permission::checkAdminUser'
);
