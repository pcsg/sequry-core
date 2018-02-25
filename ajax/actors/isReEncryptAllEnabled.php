<?php

/**
 * Checks if the re-encryption option for all access keys is enabled
 *
 * @return bool
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_isReEncryptAllEnabled',
    function () {
        return boolval(QUI::getPackage('sequry/core')->getConfig()->get(
            'settings',
            'reEncryptEnabled'
        ));
    },
    array(),
    'Permission::checkAdminUser'
);
