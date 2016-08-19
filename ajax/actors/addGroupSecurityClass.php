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
function package_pcsg_grouppasswordmanager_ajax_actors_addGroupSecurityClass($groupId, $securityClassId)
{
    $Group         = QUI::getGroups()->get((int)$groupId);
    $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);

    try {
        if (!CryptoActors::existsCryptoGroup($Group->getId())) {
            CryptoActors::createCryptoGroup($Group, $SecurityClass);
        } else {
            $CryptoGroup = CryptoActors::getCryptoGroup($Group->getId());
            $CryptoGroup->addSecurityClass($SecurityClass);
        }
    } catch (QUI\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'error.cryptogroup.securityclass.add',
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
            'pcsg/grouppasswordmanager',
            'success.cryptogroup.securityclass.add',
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
    'package_pcsg_grouppasswordmanager_ajax_actors_addGroupSecurityClass',
    array('groupId', 'securityClassId'),
    'Permission::checkAdminUser'
);
