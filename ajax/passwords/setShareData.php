<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;

/**
 * Set share data from password object
 *
 * @param integer $passwordId - ID of password
 * @param array $shareData - share users and groups
 * @param array $authData - authentication information
 * @return array - password data
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_setShareData($passwordId, $shareData, $authData)
{
    $passwordId = (int)$passwordId;

    // authenticate
    Passwords::getSecurityClass(
        $passwordId
    )->authenticate(
        json_decode($authData, true) // @todo diese daten ggf. filtern
    );

    $Password = Passwords::get($passwordId);

    $Password->setShareData(
        json_decode($shareData, true)
    );

    // get password data
    return $Password->getShareData();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_setShareData',
    array('passwordId', 'shareData', 'authData'),
    'Permission::checkAdminUser'
);