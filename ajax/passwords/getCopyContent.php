<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use Pcsg\GroupPasswordManager\PasswordTypes\Handler as TypesHandler;

/**
 * Get copy content of a password
 *
 * @param integer $passwordId - ID of password
 * @param array $authData - authentication information
 * @return string - copied content
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getCopyContent($passwordId, $authData)
{
    $passwordId = (int)$passwordId;

    // authenticate
    Passwords::getSecurityClass(
        $passwordId
    )->authenticate(
        json_decode($authData, true) // @todo diese daten ggf. filtern
    );

    $Password = Passwords::get($passwordId);
    $viewData = $Password->getViewData();

    return TypesHandler::getCopyContent($Password->getDataType(), $viewData['payload']);
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_getCopyContent',
    array('passwordId', 'authData'),
    'Permission::checkAdminUser'
);
