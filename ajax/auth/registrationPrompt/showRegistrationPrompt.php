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
        $CryptoUser                = CryptoActors::getCryptoUser();
        $unregisteredAuthPluginIds = $CryptoUser->getNonRegisteredAuthPluginIds();
        $defaultPluginId           = Authentication::getDefaultAuthPluginId();

        // First: Check if User has registered for the default authentication plugin
        // If not -> Do not show registration prompt because in this case a sepcial
        // "Welcome"-window is shown
        if (in_array($defaultPluginId, $unregisteredAuthPluginIds)) {
            return false;
        }

        $Config         = QUI::getPackage('sequry/core')->getConfig();
        $promptSettings = json_decode($Config->get('auth_plugins', 'registration'), true);

        // do not show prompt if settings have not been set yet
        if (empty($promptSettings)) {
            return false;
        }


        $passwordIds = $CryptoUser->getPasswordIds();

        foreach ($promptSettings as $authPluginId => $setting) {
            switch ($setting) {
                case 'promptIfRequired':
                case 'promptIfRequiredRegistration':
                    if (!in_array($authPluginId, $unregisteredAuthPluginIds)) {
                        continue 2;
                    }

                    foreach ($passwordIds as $passwordId) {
                        $SecurityClass = Authentication::getSecurityClassByPasswordId($passwordId);

                        if (in_array($authPluginId, $SecurityClass->getAuthPluginIds())) {
                            return true;
                        }
                    }
                    break;

                case 'promptAlways':
                case 'promptAlwaysRegistration':
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
