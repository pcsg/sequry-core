<?php

use \Sequry\Core\Security\Handler\Authentication;

/**
 * Get id, title and description of a authentication plugin
 *
 * @param integer $authPluginId - id of authentication plugin
 * @return array - id, title and description
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_getAuthPluginInfo',
    function ($authPluginId)
    {
        $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);

        return [
            'id'          => $AuthPlugin->getId(),
            'title'       => $AuthPlugin->getAttribute('title'),
            'description' => $AuthPlugin->getAttribute('description')
        ];
    },
    ['authPluginId'],
    'Permission::checkUser'
);
