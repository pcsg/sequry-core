<?php

/**
 * Register current session user and create a keypair for an authentication plugin
 *
 * @param integer $authPluginId - ID of authentication plugin
 * @param array $authData - authentication data
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_auth_registerUser($authPluginId, $authData)
{
    return \Pcsg\GroupPasswordManager\Security\Handler\Authentication::getList();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_registerUser',
    array('authPluginId'),
    'Permission::checkAdminUser'
);