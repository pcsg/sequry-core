<?php

use Pcsg\GroupPasswordManager\Security\Handler\PasswordLinks;

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
    function ($passwordId) {
        return PasswordLinks::getList((int)$passwordId);
    },
    array('passwordId'),
    'Permission::checkAdminUser'
);
