<?php

use Pcsg\GroupPasswordManager\Constants\Tables;
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
function package_pcsg_grouppasswordmanager_ajax_actors_addGroupSecurityClass($groupId, $securityClassId)
{
    // @todo PERMISSION CHECK


    $Group         = QUI::getGroups()->get((int)$groupId);
    $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);

    $result = QUI::getDataBase()->fetch(array(
        'count' => 1,
        'from'  => Tables::KEYPAIRS_GROUP,
        'where' => array(
            'groupId' => $Group->getId()
        )
    ));

    if (current(current($result)) == 0) {
        CryptoActors::createCryptoGroup($Group, $SecurityClass);
    } else {
        $CryptoGroup = CryptoActors::getCryptoGroup($Group->getId());
        $CryptoGroup->addSecurityClass($SecurityClass);
    }

    return true;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_actors_addGroupSecurityClass',
    array('groupId', 'securityClassId', 'authData'),
    'Permission::checkAdminUser'
);