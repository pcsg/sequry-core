<?php

/**
 * Register a user and create a keypair for an authentication plugin
 *
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_auth_registerUser($authPluginId)
{
    return \Pcsg\GroupPasswordManager\Security\Handler\Authentication::getList();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_registerUser',
    array('authPluginId'),
    'Permission::checkAdminUser'
);