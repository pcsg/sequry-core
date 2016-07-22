<?php

/**
 * // @todo nur als hilfs-methode, bis QUIQQER Gruppen-Panel über eine API erweitert werden können
 *
 * Get all groups
 *
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_actors_getGroups()
{
    $groups = QUI::getGroups()->getAllGroups();

    \QUI\System\Log::writeRecursive($groups);

    return $groups;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_actors_getGroups',
    array(),
    'Permission::checkAdminUser'
);