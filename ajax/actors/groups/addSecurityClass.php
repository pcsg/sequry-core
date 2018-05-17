<?php

use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Security\Handler\Authentication;

/**
 * Add security class to a CryptoGroup
 *
 * @param integer $groupId - ID of QUIQQER group
 * @param integer $securityClassId - ID of security class
 * @param integer $userId (optional) - ID of the User that is eligible for SecurityClass
 * [default: Session user]
 *
 * @return bool - success
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_groups_addSecurityClass',
    function ($groupId, $securityClassId, $userId = null) {
        try {
            $CryptoGroup   = CryptoActors::getCryptoGroup((int)$groupId);
            $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);

            $CryptoGroup->addSecurityClass($SecurityClass);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'error.cryptogroup.securityclass.add',
                    [
                        'groupId'            => $CryptoGroup->getId(),
                        'groupName'          => $CryptoGroup->getAttribute('name'),
                        'securityClassId'    => $SecurityClass->getId(),
                        'securityClassTitle' => $SecurityClass->getAttribute('title'),
                        'error'              => $Exception->getMessage()
                    ]
                )
            );

            return false;
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'sequry/core',
                'success.cryptogroup.securityclass.add',
                [
                    'groupId'            => $CryptoGroup->getId(),
                    'groupName'          => $CryptoGroup->getAttribute('name'),
                    'securityClassId'    => $SecurityClass->getId(),
                    'securityClassTitle' => $SecurityClass->getAttribute('title')
                ]
            )
        );

        return true;
    },
    ['groupId', 'securityClassId', 'userId'],
    'Permission::checkAdminUser'
);
