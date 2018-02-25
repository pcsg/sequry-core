<?php

use \Sequry\Core\Security\Handler\Authentication;

/**
 * Get id, name and description of a security class
 *
 * @param integer $securityClassId - id of security class
 * @return array - id, name and description
 */
function package_sequry_core_ajax_auth_getSecurityClassInfo($securityClassId)
{
    $SecurityClass = Authentication::getSecurityClass($securityClassId);

    $info = array(
        'id'                 => $SecurityClass->getId(),
        'title'              => $SecurityClass->getAttribute('title'),
        'description'        => $SecurityClass->getAttribute('description'),
        'groups'             => $SecurityClass->getGroupIds(),
        'requiredFactors'    => $SecurityClass->getRequiredFactors(),
        'allowPasswordLinks' => $SecurityClass->isPasswordLinksAllowed(),
        'authPlugins'        => array()
    );

    $authPlugins = $SecurityClass->getAuthPlugins();

    /** @var \Sequry\Core\Security\Authentication\Plugin $AuthPlugin */
    foreach ($authPlugins as $AuthPlugin) {
        $info['authPlugins'][] = array(
            'id'          => $AuthPlugin->getId(),
            'title'       => $AuthPlugin->getAttribute('title'),
            'description' => $AuthPlugin->getAttribute('description')
        );
    }

    return $info;
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_auth_getSecurityClassInfo',
    array('securityClassId'),
    'Permission::checkAdminUser'
);
