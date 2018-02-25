<?php

use \Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Get array with paths to all authentication controls for a specific security class
 *
 * @param integer $securityClassId - id of security class
 * @return array - data of AuthPlugin and control path
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_auth_getControlsBySecurityClass',
    function ($securityClassId)
    {
        $SecurityClass = Authentication::getSecurityClass($securityClassId);
        $authPlugins   = $SecurityClass->getAuthPlugins();
        $controls      = array();

        /** @var \Pcsg\GroupPasswordManager\Security\Authentication\Plugin $AuthPlugin */
        foreach ($authPlugins as $AuthPlugin) {
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

        if (!$authPluginSettings) {
            return $controls;
        }

        usort($controls, function ($a, $b) use ($authPluginSettings) {
            $priorityA = 0;
            $idA       = $a['authPluginId'];
            $priorityB = 0;
            $idB       = $b['authPluginId'];

            foreach ($authPluginSettings as $authPlugin) {
                if ($authPlugin['id'] == $idA) {
                    $priorityA = (int)$authPlugin['priority'];
                }

                if ($authPlugin['id'] == $idB) {
                    $priorityB = (int)$authPlugin['priority'];
                }
            }

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
    array('securityClassId'),
    'Permission::checkAdminUser'
);
