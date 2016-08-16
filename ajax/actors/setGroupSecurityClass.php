<?php

use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Set security class for a group
 *
 * @param integer $groupId - id of QUIQQER group
 * @param integer $securityClassId - id of security class
 *
 * @return bool - success
 */
function package_pcsg_grouppasswordmanager_ajax_actors_setGroupSecurityClass($groupId, $securityClassId)
{
    $Group         = QUI::getGroups()->get((int)$groupId);
    $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);

    if (!CryptoActors::existsCryptoGroup($Group->getId())) {
        CryptoActors::createCryptoGroup($Group, $SecurityClass);
    } else {
        $CryptoGroup = CryptoActors::getCryptoGroup($Group->getId());
        $CryptoGroup->setSecurityClass($SecurityClass);
    }

    return true;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_actors_setGroupSecurityClass',
    array('groupId', 'securityClassId', 'authData'),
    'Permission::checkAdminUser'
);
