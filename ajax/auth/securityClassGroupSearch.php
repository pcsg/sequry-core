<?php

use Sequry\Core\Security\Handler\Authentication;
use QUI\Utils\Security\Orthos;

/**
 * Get groups that can be added to a specific security class
 *
 * @param string $search - search term
 * @param integer $securityClassId - id of security class
 * @param integer $limit
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_securityClassGroupSearch',
    function ($search, $securityClassId, $limit) {
        $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);

        $search = Orthos::clear($search);
        $limit  = (int)$limit;

        $actors = $SecurityClass->searchGroupsToAdd($search, $limit);

        foreach ($actors as $k => $actor) {
            $actor['icon']  = 'fa fa-users';
            $actor['id']    = 'g'.$actor['id'];
            $actor['title'] = $actor['name'];

            $actors[$k] = $actor;
        }

        return $actors;
    },
    ['search', 'securityClassId', 'limit'],
    'Permission::checkAdminUser'
);
