<?php

/**
 * Get list of VHosts
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_passwords_link_getVHostList',
    function () {
        $VhostManager = new \QUI\System\VhostManager();
        return array_keys($VhostManager->getList());
    },
    false
);
