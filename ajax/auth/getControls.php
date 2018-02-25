<?php

use \Sequry\Core\Security\Handler\Authentication;

/**
 * Get array with paths to all authentication controls for a specific security class
 *
 * @param integer $authPluginIds - IDs of authentication plugins
 * @return array - data of AuthPlugin and control path
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_getControls',
    function ($authPluginIds) {
        $controls      = array();
        $authPluginIds = json_decode($authPluginIds, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $controls;
        }

        /** @var \Sequry\Core\Security\Authentication\Plugin $AuthPlugin */
        foreach ($authPluginIds as $authPluginId) {
            try {
                $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
            } catch (\Exception $Exception) {
                continue;
            }

            $controls[] = array(
                'authPluginId' => $AuthPlugin->getId(),
                'title'        => $AuthPlugin->getAttribute('title'),
                'control'      => $AuthPlugin->getAuthenticationControl(),
                'registered'   => $AuthPlugin->isRegistered()
            );
        }

        // sort by user priority
        $User               = QUI::getUserBySession();
        $authPluginSettings = json_decode($User->getAttribute('pcsg.gpm.settings.authplugins'), true);

        foreach ($controls as $k => $authPluginData) {
            if (empty($authPluginSettings)) {
                $controls[$k]['priority'] = 1;
                continue;
            }

            foreach ($authPluginSettings as $authPlugin) {
                if ($authPluginData['authPluginId'] == $authPlugin['id']) {
                    $controls[$k]['priority'] = $authPlugin['priority'];
                    continue 2;
                }
            }
        }

        if (!$authPluginSettings) {
            return $controls;
        }

        usort($controls, function ($a, $b) {
            $priorityA = $a['priority'];
            $priorityB = $b['priority'];

            if ($priorityA === $priorityB) {
                return 0;
            }

            return $priorityA > $priorityB ? 0 : 1;
        });

        foreach ($controls as $k => $control) {
            $id = $control['authPluginId'];

            foreach ($authPluginSettings as $authPlugin) {
                if ($id == $authPlugin['id']) {
                    $control['autosave'] = $authPlugin['autosave'];
                    break;
                }
            }

            $controls[$k] = $control;
        }

        return $controls;
    },
    array('authPluginIds'),
    'Permission::checkAdminUser'
);
