<?php

use Sequry\Core\Security\Handler\CryptoActors;

/**
 * Get group administration information for current session user
 *
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_users_getGroupAdminStatus',
    function () {
        $CryptoUser  = CryptoActors::getCryptoUser();
        $adminGroups = $CryptoUser->getAdminGroups();
        $status      = array(
            'isGroupAdmin' => false,
            'openRequests' => 0
        );

        if (empty($adminGroups)) {
            return $status;
        }

        $status['isGroupAdmin'] = true;

        foreach ($adminGroups as $CryptoGroup) {
            $status ['openRequests'] += count($CryptoGroup->getNoAccessUserIds());
        }

        return $status;
    },
    array(),
    'Permission::checkAdminUser'
);
