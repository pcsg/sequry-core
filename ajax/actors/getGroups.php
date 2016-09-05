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

        $groupData['securityClasses'] = array();

        // @todo
        if (CryptoActors::existsCryptoGroup($groupData['id'])) {
            $CryptoGroup     = CryptoActors::getCryptoGroup($groupData['id']);
            $securityClasses = $CryptoGroup->getSecurityClasses();

            /** @var \Pcsg\GroupPasswordManager\Security\Authentication\SecurityClass $SecurityClass */
            foreach ($securityClasses as $SecurityClass) {
                $groupData['securityClasses'][] = $SecurityClass->getAttribute('title');
            }
        }

        $groups[] = $groupData;
    }

    return $groups;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_actors_getGroups',
    array(),
    'Permission::checkAdminUser'
);
