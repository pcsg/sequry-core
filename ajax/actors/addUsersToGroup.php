<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

/**
 * Add user(s) to a group
 *
 * @param integer $groupId - id of crypto group
 * @param array $userIds - IDs of users that shall be added to the group
 * @param array $authData - authentication information for all relevant security classes
 * @return bool - success
 */
function package_pcsg_grouppasswordmanager_ajax_actors_addUsersToGroup($groupId, $userIds, $authData)
{
    $authData = json_decode($authData, true); // @todo ggf. filtern

    foreach ($authData as $securityClassId => $securityClassAuthData) {
        try {
            $SecurityClass = Authentication::getSecurityClass($securityClassId);
            $SecurityClass->authenticate($securityClassAuthData);
        } catch (\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'error.authentication.incorrect.auth.data'
                )
            );

            return false;
        }
    }

    $userIds = json_decode($userIds, true);

    try {
        $CryptoGroup = CryptoActors::getCryptoGroup((int)$groupId);

        foreach ($userIds as $userId) {
            $CryptoUser = CryptoActors::getCryptoUser((int)$userId);
            $CryptoGroup->addUser($CryptoUser);
            $CryptoUser->save();
        }
    } catch (\Exception $Exception) {
        QUI\System\Log::addError(
            'AJAX: actors/addUsersToGroup error: ' . $Exception->getMessage()
        );

        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'error.actors.adduserstogroup',
                array(
                    'groupId' => (int)$groupId
                )
            )
        );

        return false;
    }

    QUI::getMessagesHandler()->addSuccess(
        QUI::getLocale()->get(
            'pcsg/grouppasswordmanager',
            'success.actors.adduserstogroup',
            array(
                'groupId'   => $CryptoGroup->getId(),
                'groupName' => $CryptoGroup->getName()
            )
        )
    );

    return true;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_actors_addUsersToGroup',
    array('groupId', 'userIds', 'authData'),
    'Permission::checkAdminUser'
);
