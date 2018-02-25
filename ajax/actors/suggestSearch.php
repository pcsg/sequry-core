<?php

use Sequry\Core\Security\Handler\Authentication;
use QUI\Utils\Security\Orthos;

/**
 * Get password actor info (user/group)
 *
 * @param string $search - search term
 * @param string $type - "user" or "group"
 * @param integer $securityClassId - id of security class
 * @param integer $limit
 * @return array
 */
function package_sequry_core_ajax_actors_suggestSearch($search, $type, $securityClassId, $limit)
{
    $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);

    $search = Orthos::clear($search);
    $limit  = (int)$limit;

    $actors = $SecurityClass->suggestSearchEligibleActors($search, $type, $limit);

    foreach ($actors as $k => $actor) {
        switch ($actor['type']) {
            case 'user':
//                if ($actor['id'] == $CryptoUsers->getId()) {
//                    unset($actors[$k]);
//                    continue 2;
//                }

                $actor['icon'] = 'fa fa-user';
                $actor['id']   = 'u' . $actor['id'];
                break;

            case 'group':
                $actor['icon'] = 'fa fa-users';
                $actor['id']   = 'g' . $actor['id'];
                break;
        }

        $actor['title'] = $actor['name'];

        $actors[$k] = $actor;
    }

    return array_values($actors);
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_actors_suggestSearch',
    array('search', 'type', 'securityClassId', 'limit'),
    'Permission::checkAdminUser'
);
