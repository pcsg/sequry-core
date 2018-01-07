<?php

use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

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
    'package_pcsg_grouppasswordmanager_ajax_actors_groups_addSecurityClass',
    function ($groupId, $securityClassId, $userId = null) {
        try {
            $Group         = QUI::getGroups()->get((int)$groupId);
            $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);
            $User          = null;

            if (!empty($userId)) {
                $User = QUI::getUsers()->get((int)$userId);
            }

            CryptoActors::createCryptoGroupKey($Group, $SecurityClass, $User);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

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
    },
    array('groupId', 'securityClassId', 'userId'),
    'Permission::checkAdminUser'
);
