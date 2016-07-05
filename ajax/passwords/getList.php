<?php

/**
 * Get a list of all passwords the currently logged in user has access to
 *
 * @param string $searchParams - search options [json]
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getList($searchParams)
{
//    QUI\Rights\Permission::checkPermission(
//        'hklused.machines.category.list.view'
//    );

    \Pcsg\GroupPasswordManager\Security\AsymmetricCrypto::generateKeyPair();

    $CryptoUser = \Pcsg\GroupPasswordManager\Security\Handler\CryptoActors::getCryptoUser();

    $searchParams = \QUI\Utils\Security\Orthos::clearArray(
        json_decode($searchParams, true)
    );

    return $CryptoUser->getPasswords($searchParams);
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_getList',
    array('searchParams'),
    'Permission::checkAdminUser'
);