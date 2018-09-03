<?php

use \Sequry\Core\Security\Handler\Authentication;
use \Sequry\Core\Security\Authentication\Plugin;

/**
 * Checks the auth status for authentication plugins
 *
 * @param array $authPluginIds - ids of AuthPlugins
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_checkAuthStatus',
    function ($authPluginIds) {
        $authStatus = [
            'authPlugins'      => [],
            'authenticatedAll' => false
        ];

        $authPluginIds = json_decode($authPluginIds, true);
        $authCounter   = 0;

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $authStatus;
        }

        /** @var Plugin $AuthPlugin */
        foreach ($authPluginIds as $authPluginId) {
            try {
                $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
            } catch (\Exception $Exception) {
                continue;
            }

            $authStatus['authPlugins'][$AuthPlugin->getId()] = $AuthPlugin->isAuthenticated();

            if ($authStatus['authPlugins'][$AuthPlugin->getId()]) {
                $authCounter++;
            }
        }

        if ($authCounter === count($authPluginIds)) {
            $authStatus['authenticatedAll'] = true;
        }

        $authStatus['authenticatedCount'] = $authCounter;

        return $authStatus;
    },
    ['authPluginIds'],
    'Permission::checkUser'
);
