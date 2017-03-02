<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Get users that are eligible for a specific security class
 *
 * @param integer $securityClassId - ID of security class
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_auth_getEligibleUsers($securityClassId)
{
    $SecurityClass    = Authentication::getSecurityClass((int)$securityClassId);
    $eligibleUserIds  = $SecurityClass->getEligibleUserIds();
    $eligibleUserData = array();

    foreach ($eligibleUserIds as $userId) {
        $User = QUI::getUsers()->get($userId);

        $eligibleUserData[] = array(
            'id'       => $User->getId(),
            'username' => $User->getName()
        );
    }

    return $eligibleUserData;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_getEligibleUsers',
    array('securityClassId'),
    'Permission::checkAdminUser'
);
