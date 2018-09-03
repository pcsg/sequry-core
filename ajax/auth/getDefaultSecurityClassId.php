<?php

/**
 * Get ID of the default security class
 *
 * @return int|false - security class ID or false if not set
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_getDefaultSecurityClassId',
    function () {
        $Config = QUI::getPackage('sequry/core')->getConfig();
        $id     = $Config->get('settings', 'defaultSecurityClassId');

        if (empty($id)) {
            return false;
        }

        return (int)$id;
    },
    [],
    'Permission::checkUser'
);
