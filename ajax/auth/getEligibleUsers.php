<?php

use Sequry\Core\Security\Handler\Authentication;

/**
 * Get users that are eligible for a specific security class
 *
 * @param integer $securityClassId - ID of security class
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_getEligibleUsers',
    function ($securityClassId) {
        $SecurityClass    = Authentication::getSecurityClass((int)$securityClassId);
        $eligibleUserIds  = $SecurityClass->getEligibleUserIds();
        $eligibleUserData = [];

        foreach ($eligibleUserIds as $userId) {
            $User = QUI::getUsers()->get($userId);

            $eligibleUserData[] = [
                'id'       => $User->getId(),
                'username' => $User->getName()
            ];
        }

        return $eligibleUserData;
    },
    ['securityClassId'],
    'Permission::checkUser'
);
