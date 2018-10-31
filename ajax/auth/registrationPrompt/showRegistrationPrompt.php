<?php

use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\CryptoActors;

/**
 * Check if the authentication plugin registration prompt window should be shown
 *
 * @return bool
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_registrationPrompt_showRegistrationPrompt',
    function () {
        $Config         = QUI::getPackage('sequry/core')->getConfig();
        $promptSettings = json_decode($Config->get('auth_plugins', 'registration'), true);
        $CryptoUser     = CryptoActors::getCryptoUser();

        foreach ($promptSettings as $authPluginId => $setting) {
            switch ($setting) {
                case 'promptIfRequired':
                case 'promptIfRequiredRegistration':
                    $AuthPlugin   = Authentication::getAuthPlugin($authPluginId);
                    $nonAccessIds = $CryptoUser->getNonFullyAccessiblePasswordIds($AuthPlugin, false);

                    if (!empty($nonAccessIds)) {
                        return true;
                    }
                    break;

                case 'promptAlways':
                case 'promptAlwaysRegistration':
                    $unregisteredAuthPluginIds = $CryptoUser->getNonRegisteredAuthPluginIds();

                    if (in_array($authPluginId, $unregisteredAuthPluginIds)) {
                        return true;
                    }
                    break;
            }
        }

        return false;
    },
    [],
    'Permission::checkUser'
);
