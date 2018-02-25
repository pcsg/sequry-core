<?php

use Sequry\Core\Security\Handler\CryptoActors;

/**
 * Get IDs of all security classes of a group
 *
 * @param integer $groupId - id of QUIQQER group
 * @return int[]|false
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_getGroupsSecurityClassIds',
    function ($groupIds) {
        $groupIds = json_decode($groupIds, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        $securityClassIds = array();

        try {
            foreach ($groupIds as $groupId) {
                $Group = CryptoActors::getCryptoGroup((int)$groupId);

                $securityClassIds = array_merge(
                    $securityClassIds,
                    $Group->getSecurityClassIds()
                );
            }

            return $securityClassIds;
        } catch (\Exception $Exception) {
            return false;
        }
    },
    array('groupIds'),
    'Permission::checkAdminUser'
);
