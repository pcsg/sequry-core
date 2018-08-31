<?php

/**
 * Get settings for password creation
 *
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_create_getSettings',
    function () {
        $Conf     = QUI::getPackage('sequry/core')->getConfig();
        $settings = $Conf->getSection('settings');

        return [
            'actorTypePasswordCreate' => $settings['actorTypePasswordCreate']
        ];
    },
    [],
    'Permission::checkUser'
);
