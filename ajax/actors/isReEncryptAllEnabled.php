<?php

/**
 * Checks if the re-encryption option for all access keys is enabled
 *
 * @return bool
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_actors_isReEncryptAllEnabled',
    function () {
        return boolval(QUI::getPackage('pcsg/grouppasswordmanager')->getConfig()->get(
            'settings',
            'reEncryptEnabled'
        ));
    },
    array(),
    'Permission::checkAdminUser'
);
