<?php

/**
 * Get a list of all passwords the currently logged in user has access to
 *
 * @param string $searchParams - search options [json]
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getList($searchParams)
{
    ini_set('display_errors', 1);
//    QUI\Rights\Permission::checkPermission(
//        'hklused.machines.category.list.view'
//    );

    \Pcsg\GroupPasswordManager\Security\AsymmetricCrypto::generateKeyPair();

    $CryptoUser = \Pcsg\GroupPasswordManager\Security\Handler\CryptoActors::getCryptoUser();

    $searchParams = \QUI\Utils\Security\Orthos::clearArray(
        json_decode($searchParams, true)
    );

    $Grid = new \QUI\Utils\Grid($searchParams);
    $passwords = $CryptoUser->getPasswordList($searchParams);

    return $Grid->parseResult(
        $passwords,
        $CryptoUser->getPasswordList($searchParams, true)
    );
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_getList',
    array('searchParams'),
    'Permission::checkAdminUser'
);