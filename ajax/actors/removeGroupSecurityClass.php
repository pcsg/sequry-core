<?php

use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Security\Handler\Authentication;

/**
 * Remove security class from a group
 *
 * @param integer $groupId - id of QUIQQER group
 * @param integer $securityClassId - id of security class
 *
 * @return bool - success
 */
function package_sequry_core_ajax_actors_removeGroupSecurityClass($groupId, $securityClassId)
{
    $Group         = QUI::getGroups()->get((int)$groupId);
    $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);

    try {
        $CryptoGroup = CryptoActors::getCryptoGroup($Group->getId());
        $CryptoGroup->removeSecurityClass($SecurityClass);
    } catch (QUI\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'sequry/core',
                'error.cryptogroup.securityclass.remove',
                array(
                    'groupId'            => $Group->getId(),
                    'groupName'          => $Group->getAttribute('name'),
                    'securityClassId'    => $SecurityClass->getId(),
                    'securityClassTitle' => $SecurityClass->getAttribute('title'),
                    'error'              => $Exception->getMessage()
                )
            )
        );

        return false;
    }

    QUI::getMessagesHandler()->addSuccess(
        QUI::getLocale()->get(
            'sequry/core',
            'success.cryptogroup.securityclass.remove',
            array(
                'groupId'            => $Group->getId(),
                'groupName'          => $Group->getAttribute('name'),
                'securityClassId'    => $SecurityClass->getId(),
                'securityClassTitle' => $SecurityClass->getAttribute('title')
            )
        )
    );

    return true;
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_actors_removeGroupSecurityClass',
    array('groupId', 'securityClassId'),
    'Permission::checkAdminUser'
);
