<?php

use \Sequry\Core\Security\Handler\Authentication;

/**
 * Get id, title and description of a authentication plugin
 *
 * @param integer $authPluginId - id of authentication plugin
 * @return array - id, title and description
 */
function package_sequry_core_ajax_auth_getAuthPluginInfo($authPluginId)
{
    $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);

    return array(
        'id'          => $AuthPlugin->getId(),
        'title'       => $AuthPlugin->getAttribute('title'),
        'description' => $AuthPlugin->getAttribute('description')
    );
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_auth_getAuthPluginInfo',
    array('authPluginId'),
    'Permission::checkAdminUser'
);
