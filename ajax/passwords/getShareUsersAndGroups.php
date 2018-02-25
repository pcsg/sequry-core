<?php

use Sequry\Core\Security\Handler\Passwords;

/**
 * Get all users and groups a password is shared with
 *
 * @param integer $passwordId - ID of password
 * @return array - password data
 */
function package_sequry_core_ajax_passwords_getShareUsersAndGroups($passwordId)
{
    $passwordId = (int)$passwordId;

    // get password data
    $shareData   = Passwords::get($passwordId)->getShareData();
    $sharedWith  = $shareData['sharedWith'];
    $usersGroups = array(
        'users'  => array(),
        'groups' => array()
    );

    foreach ($sharedWith['users'] as $userId) {
        $User                   = QUI::getUsers()->get($userId);
        $usersGroups['users'][] = array(
            'id'   => $userId,
            'name' => $User->getName()
        );
    }

    foreach ($sharedWith['groups'] as $groupId) {
        $Group                   = QUI::getGroups()->get($groupId);
        $usersGroups['groups'][] = array(
            'id'   => $groupId,
            'name' => $Group->getName()
        );
    }

    return $usersGroups;
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_passwords_getShareUsersAndGroups',
    array('passwordId'),
    'Permission::checkAdminUser'
);
