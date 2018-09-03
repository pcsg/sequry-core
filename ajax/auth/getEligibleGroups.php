<?php

use Sequry\Core\Security\Handler\Authentication;

/**
 * Get groups that are eligible for a specific security class
 *
 * @param integer $securityClassId - ID of security class
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_getEligibleGroups',
    function ($securityClassId)
    {
        $SecurityClass     = Authentication::getSecurityClass((int)$securityClassId);
        $eligibleGroupIds  = $SecurityClass->getGroupIds();
        $eligibleGroupData = [];

        foreach ($eligibleGroupIds as $groupId) {
            $Group = QUI::getGroups()->get($groupId);

            $eligibleGroupData[] = [
                'id'   => $Group->getId(),
                'name' => $Group->getAttribute('name')
            ];
        }

        return $eligibleGroupData;
    },
    ['securityClassId'],
    'Permission::checkUser'
);
