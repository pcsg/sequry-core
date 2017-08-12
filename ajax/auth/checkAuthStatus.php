<?php

use \Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use \Pcsg\GroupPasswordManager\Security\Authentication\Plugin;

/**
 * Checks the auth status for authentication plugins
 *
 * @param array $authPluginIds - ids of AuthPlugins
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_auth_checkAuthStatus',
    function ($authPluginIds) {
        $authStatus = array(
            'authPlugins'      => array(),
            'authenticatedAll' => false
        );

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

        return $authStatus;
    },
    array('authPluginIds'),
    'Permission::checkAdminUser'
);
