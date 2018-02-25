<?php

/**
 * Get settings for password creation
 *
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_passwords_create_getSettings',
    function () {
        $Conf     = QUI::getPackage('pcsg/grouppasswordmanager')->getConfig();
        $settings = $Conf->getSection('settings');

        return array(
            'actorTypePasswordCreate' => $settings['actorTypePasswordCreate']
        );
    },
    array(),
    'Permission::checkAdminUser'
);
