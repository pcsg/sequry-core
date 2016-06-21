<?php

/**
 * Get all available security classes that are registered
 *
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_auth_getSecurityClasses()
{
    return \Pcsg\GroupPasswordManager\Security\Handler\SecurityClasses::getList();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_getSecurityClasses',
    array(),
    'Permission::checkAdminUser'
);