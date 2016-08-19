<?php

use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

/**
 * // @todo nur als hilfs-methode, bis QUIQQER Gruppen-Panel über eine API erweitert werden können
 *
 * Get all groups
 *
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_actors_getGroups()
{
    $quiqqerGroups = QUI::getGroups()->getAllGroups();
    $groups        = array();

    foreach ($quiqqerGroups as $groupData) {
        switch ((int)$groupData['id']) {
            // ignore special groups
            case 0:
            case 1:
                continue 2;
                break;
        }

        $securityClass = false;

        // @todo
//        if (CryptoActors::existsCryptoGroup($groupData['id'])) {
//            $CryptoGroup   = CryptoActors::getCryptoGroup($groupData['id']);
//            $SecurityClass = $CryptoGroup->getSecurityClass();
//            $securityClass = $SecurityClass->getAttribute('title');
//        }

        $groupData['securityClass'] = $securityClass;

        $groups[] = $groupData;
    }

    return $groups;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_actors_getGroups',
    array(),
    'Permission::checkAdminUser'
);
