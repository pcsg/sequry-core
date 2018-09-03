<?php

use Sequry\Core\Security\Handler\Passwords;

/**
 * Get all users and groups a password is shared with
 *
 * @param integer $passwordId - ID of password
 * @return array - password data
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_getShareUsersAndGroups',
    function ($passwordId) {
        $passwordId = (int)$passwordId;

        // get password data
        $shareData   = Passwords::get($passwordId)->getShareData();
        $sharedWith  = $shareData['sharedWith'];
        $usersGroups = [
            'users'  => [],
            'groups' => []
        ];

        foreach ($sharedWith['users'] as $userId) {
            $User                   = QUI::getUsers()->get($userId);
            $usersGroups['users'][] = [
                'id'   => $userId,
                'name' => $User->getName()
            ];
        }

        foreach ($sharedWith['groups'] as $groupId) {
            $Group                   = QUI::getGroups()->get($groupId);
            $usersGroups['groups'][] = [
                'id'   => $groupId,
                'name' => $Group->getName()
            ];
        }

        return $usersGroups;
    },
    ['passwordId'],
    'Permission::checkUser'
);
