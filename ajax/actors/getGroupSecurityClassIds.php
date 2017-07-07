<?php

use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

/**
 * Get IDs of all security classes of a group
 *
 * @param integer $groupId - id of QUIQQER group
 * @return int[]|false
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_actors_getGroupSecurityClassIds',
    function ($groupId) {
        try {
            $Group = CryptoActors::getCryptoGroup((int)$groupId);
            return $Group->getSecurityClassIds();
        } catch (\Exception $Exception) {
            return false;
        }
    },
    array('groupId'),
    'Permission::checkAdminUser'
);
