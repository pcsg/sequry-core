<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use QUI\Utils\Security\Orthos;

/**
 * Get password actor info (user/group)
 *
 * @param string $search - search term
 * @param string $type - "user" or "group"
 * @param array $securityClassIds - ids of security classes the actors or groups must be eligible for
 * @param integer $limit
 * @return array
 *
 * @throws QUI\Exception
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_actors_suggestSearch',
    function ($search, $type, $securityClassIds, $limit) {
        $securityClassIds = json_decode($securityClassIds, true);
        $search           = Orthos::clear($search);
        $limit            = (int)$limit;
        $users            = array();
        $groups           = array();
        $allActors        = array();

        foreach ($securityClassIds as $securityClassId) {
            $securityClassId = (int)$securityClassId;
            $SecurityClass   = Authentication::getSecurityClass($securityClassId);
            $actors          = $SecurityClass->suggestSearchEligibleActors($search, $type, $limit);
            $allActors       = array_merge($allActors, $actors);

            $users[$securityClassId]  = array();
            $groups[$securityClassId] = array();

            foreach ($actors as $actor) {
                switch ($actor['type']) {
                    case 'user':
                        $users[$securityClassId][] = $actor['id'];
                        break;

                    case 'group':
                        $groups[$securityClassId][] = $actor['id'];
                        break;
                }
            }
        }

        // filter only those users and groups that are eligible for all given SecurityClasses
        if (count($securityClassIds) > 1) {
            $eligibleUserIds  = call_user_func_array('array_intersect', array_values($users));
            $eligibleGroupIds = call_user_func_array('array_intersect', array_values($groups));
            $eligibleActors   = array();

            // users
            foreach ($eligibleUserIds as $userId) {
                foreach ($allActors as $k => $actor) {
                    if ($actor['type'] === 'user' && $actor['id'] === $userId) {
                        $eligibleActors[] = $actor;
                        break;
                    }
                }
            }

            // groups
            foreach ($eligibleGroupIds as $groupId) {
                foreach ($allActors as $k => $actor) {
                    if ($actor['type'] === 'group' && $actor['id'] === $groupId) {
                        $eligibleActors[] = $actor;
                        break;
                    }
                }
            }
        } else {
            $eligibleActors = $allActors;
        }

        foreach ($eligibleActors as $k => $actor) {
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

            $actor['title']     = $actor['name'];
            $eligibleActors[$k] = $actor;
        }

        return array_values($eligibleActors);
    },
    array('search', 'type', 'securityClassIds', 'limit'),
    'Permission::checkAdminUser'
);
