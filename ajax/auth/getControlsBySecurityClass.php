<?php

use \Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Get array with paths to all authentication controls for a specific security class
 *
 * @param integer $securityClassId - id of security class
 * @return string - path to javascript control
 */
function package_pcsg_grouppasswordmanager_ajax_auth_getControlsBySecurityClass($securityClassId)
{
    $SecurityClass = Authentication::getSecurityClass($securityClassId);
    $authPlugins   = $SecurityClass->getAuthPlugins();
    $controls      = array();

    /** @var \Pcsg\GroupPasswordManager\Security\Authentication\Plugin $AuthPlugin */
    foreach ($authPlugins as $AuthPlugin) {
        $controls[$AuthPlugin->getId()] = array(
            'control'    => $AuthPlugin->getAuthenticationControl(),
            'registered' => $AuthPlugin->isRegistered()
        );
    }

    return $controls;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_getControlsBySecurityClass',
    array('securityClassId'),
    'Permission::checkAdminUser'
);
