<?php

use \Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Get IDs of authentication plugins for SecurityClass(es)
 *
 * @param array $securityClassIds - IDs of security classes
 * @return array - IDs of authentication plugins per SecurityClass
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_auth_getAuthPluginIdsBySecurityClass',
    function ($securityClassIds) {
        $authPluginIds    = array();
        $securityClassIds = json_decode($securityClassIds, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $authPluginIds;
        }

        foreach ($securityClassIds as $securityClassId) {
            try {
                $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);
            } catch (\Exception $Exception) {
                continue;
            }

            $authPluginIds[$SecurityClass->getId()] = $SecurityClass->getAuthPluginIds();
        }

        return $authPluginIds;
    },
    array('securityClassIds'),
    'Permission::checkAdminUser'
);
