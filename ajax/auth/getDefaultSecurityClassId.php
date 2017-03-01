<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

/**
 * Get ID of the default security class
 *
 * @return int|false - security class ID or false if not set
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_auth_getDefaultSecurityClassId',
    function () {
        $Config = QUI::getPackage('pcsg/grouppasswordmanager')->getConfig();
        $id     = $Config->get('settings', 'defaultSecurityClassId');

        if (empty($id)) {
            return false;
        }

        return (int)$id;
    },
    array(),
    'Permission::checkAdminUser'
);
