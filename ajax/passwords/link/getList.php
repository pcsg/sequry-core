<?php

use Pcsg\GroupPasswordManager\Security\Handler\PasswordLinks;
use QUI\Utils\Security\Orthos;
use QUI\Utils\Grid;

/**
 * Get list of all PasswordLinks for a specific password
 *
 * @param integer $passwordId - ID of password
 * @param array $linkData - settings for PasswordLink
 * @return bool - success
 *
 * @throws QUI\Exception
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_passwords_link_getList',
    function ($passwordId, $searchParams) {
        $searchParams = Orthos::clearArray(
            json_decode($searchParams, true)
        );

        $Grid = new Grid($searchParams);

        return $Grid->parseResult(
            PasswordLinks::getList((int)$passwordId, $searchParams),
            PasswordLinks::getList((int)$passwordId, $searchParams, true)
        );
    },
    array('passwordId', 'searchParams'),
    'Permission::checkAdminUser'
);
