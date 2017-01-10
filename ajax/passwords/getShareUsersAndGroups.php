<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Get all users and groups a password is shared with
 *
 * @param integer $passwordId - ID of password
 * @param array $authData - authentication information
 * @return array - password data
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getShareUsersAndGroups($passwordId, $authData)
{
    $passwordId = (int)$passwordId;

    // authenticate
    Passwords::getSecurityClass(
        $passwordId
    )->authenticate(
        json_decode($authData, true) // @todo diese daten ggf. filtern
    );

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
            'name' => $User->getUsername()
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
    'package_pcsg_grouppasswordmanager_ajax_passwords_getShareUsersAndGroups',
    array('passwordId', 'authData'),
    'Permission::checkAdminUser'
);
