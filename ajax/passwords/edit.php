<?php

use QUI\Utils\Security\Orthos;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Edit a password object
 *
 * @param integer $passwordId - ID of password
 * @param array $passwordData - edited data of password
 * @param array $authData - authentication information
 * @return array - new pasword data
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_edit($passwordId, $passwordData, $authData)
{
    $passwordId = (int)$passwordId;

    // authenticate
    Passwords::getSecurityClass(
        $passwordId
    )->authenticate(
        json_decode($authData, true) // @todo diese daten ggf. filtern
    );

    // edit password
    $Password = Passwords::get($passwordId);

    $Password->setData(
        Orthos::clearArray(json_decode($passwordData, true))
    );

    return $Password->getData();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_edit',
    array('passwordId', 'passwordData', 'authData'),
    'Permission::checkAdminUser'
);