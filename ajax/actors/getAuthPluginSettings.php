<?php

/**
 * Get auth plugin settings for current session user
 *
 * @return array|false - settings for authplugin or false if no settings set
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_getAuthPluginSettings',
    function () {
        $User               = QUI::getUserBySession();
        $authPluginSettings = $User->getAttribute('pcsg.gpm.settings.authplugins');

        if (!$authPluginSettings) {
            return false;
        }

        return json_decode($authPluginSettings, true);
    },
    [],
    'Permission::checkUser'
);
