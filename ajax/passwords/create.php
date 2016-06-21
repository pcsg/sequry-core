<?php

/**
 * Create a new password object
 *
 * @param string $searchParams - search options [json]
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_create($passwordData)
{
//    QUI\Rights\Permission::checkPermission(
//        'hklused.machines.category.list.view'
//    );

    $CryptoUser = new \Pcsg\GroupPasswordManager\CryptoUser();

    $passwordData = \QUI\Utils\Security\Orthos::clearArray(
        json_decode($passwordData, true)
    );

//    return $CryptoUser->getPasswords($searchParams);
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_create',
    array('passwordData'),
    'Permission::checkAdminUser'
);