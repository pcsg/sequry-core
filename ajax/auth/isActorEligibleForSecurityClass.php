<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

/**
 * Checks if the given user is eligible for the given security class
 *
 * @param integer $actorId - user or group ID
 * @param string $actorType - "user" / "group"
 * @param integer $securityClassId - ID of security class
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_auth_isActorEligibleForSecurityClass',
    function ($actorId, $actorType, $securityClassId) {
        try {
            $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);

            if ($actorType === 'user') {
                $CryptoActor = CryptoActors::getCryptoUser((int)$actorId);
                return $SecurityClass->isUserEligible($CryptoActor);
            } else {
                $CryptoActor = CryptoActors::getCryptoGroup((int)$actorId);
                return $SecurityClass->isGroupEligible($CryptoActor);
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_pcsg_grouppasswordmanager_ajax_auth_isActorEligibleForSecurityClass -> ' . $Exception->getMessage()
            );

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'message.general.error'
                )
            );

            return false;
        }
    },
    array('actorId', 'actorType', 'securityClassId'),
    'Permission::checkAdminUser'
);
