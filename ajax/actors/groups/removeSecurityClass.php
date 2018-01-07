<?php

use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Remove security class from a group
 *
 * @param integer $groupId - id of QUIQQER group
 * @param integer $securityClassId - id of security class
 *
 * @return bool - success
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_actors_groups_removeSecurityClass',
    function ($groupId, $securityClassId) {
        try {
            $Group         = QUI::getGroups()->get((int)$groupId);
            $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);

            $CryptoGroup = CryptoActors::getCryptoGroup($Group->getId());
            $CryptoGroup->removeSecurityClass($SecurityClass);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
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
                'pcsg/grouppasswordmanager',
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
    },
    array('groupId', 'securityClassId'),
    'Permission::checkAdminUser'
);
