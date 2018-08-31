<?php

use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Security\Handler\Authentication;

/**
 * Get array with paths to all authentication controls for a specific user
 *
 * @return array - data for each control
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_getControlsByUser',
    function () {
        $CryptoUser     = CryptoActors::getCryptoUser();
        $authKeyPairIds = $CryptoUser->getAuthKeyPairIds();
        $controls       = [];

        /** @var \Sequry\Core\Security\Keys\AuthKeyPair $AuthKeyPair */
        foreach ($authKeyPairIds as $authKeyPairId) {
            $AuthKeyPair = Authentication::getAuthKeyPair($authKeyPairId);
            $AuthPlugin  = $AuthKeyPair->getAuthPlugin();

            $controls[] = [
                'authPluginId' => $AuthPlugin->getId(),
                'title'        => $AuthPlugin->getAttribute('title'),
                'control'      => $AuthPlugin->getAuthenticationControl(),
                'registered'   => $AuthPlugin->isRegistered()
            ];
        }

        return $controls;
    },
    [],
    'Permission::checkUser'
);
