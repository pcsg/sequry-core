<?php

use Sequry\Core\Security\Handler\Authentication;

/**
 * Get groups that are eligible for a specific security class
 *
 * @param integer $securityClassId - ID of security class
 * @return array
 */
function package_sequry_core_ajax_auth_getEligibleGroups($securityClassId)
{
    $SecurityClass     = Authentication::getSecurityClass((int)$securityClassId);
    $eligibleGroupIds  = $SecurityClass->getGroupIds();
    $eligibleGroupData = array();

    foreach ($eligibleGroupIds as $groupId) {
        $Group = QUI::getGroups()->get($groupId);

        $eligibleGroupData[] = array(
            'id'   => $Group->getId(),
            'name' => $Group->getAttribute('name')
        );
    }

    return $eligibleGroupData;
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_auth_getEligibleGroups',
    array('securityClassId'),
    'Permission::checkAdminUser'
);
