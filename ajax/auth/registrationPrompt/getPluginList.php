<?php

use Sequry\Core\Security\Handler\Authentication;

/**
 * Get a list of all installed authentication plugins and their
 * (required) registration status
 *
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_registrationPrompt_getPluginList',
    function () {
        $list           = Authentication::getAuthPluginList();
        $Config         = QUI::getPackage('sequry/core')->getConfig();
        $promptSettings = json_decode($Config->get('auth_plugins', 'registration'), true);

        foreach ($list as $k => $authPlugin) {
            if (!isset($promptSettings[$authPlugin['id']])) {
                continue;
            }

            $required = false;

            switch ($promptSettings[$authPlugin['id']]) {
                case 'promptIfRequiredRegistration':
                case 'promptAlwaysRegistration':
                    $required = true;
                    break;
            }

            $list[$k]['registrationRequired'] = $required;
        }

        return $list;
    },
    [],
    'Permission::checkUser'
);
