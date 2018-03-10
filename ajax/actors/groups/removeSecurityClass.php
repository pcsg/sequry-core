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
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_groups_removeSecurityClass',
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
    },
    array('groupId', 'securityClassId'),
    'Permission::checkAdminUser'
);
