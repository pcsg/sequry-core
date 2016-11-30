<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Events;

/**
 * Add group(s) to a user
 *
 * @param integer $userId - id of CryptoUser
 * @param array $groupIds - IDs of groups that shall be added to the user
 * @param array $authData - authentication information for all relevant security classes
 * @return bool - success
 */
function package_pcsg_grouppasswordmanager_ajax_actors_addGroupsToUser($userId, $groupIds, $authData)
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
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_actors_addGroupsToUser',
    array('userId', 'groupIds', 'authData'),
    'Permission::checkAdminUser'
);
