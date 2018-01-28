<?php

use QUI\Utils\Security\Orthos;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Authorize access for certain users for groups and securityclasses
 *
 * @param array $unlockRequests
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_actors_groups_unlockUsers',
    function ($unlockRequests) {
        try {
            $unlockRequests = Orthos::clearArray(
                json_decode($unlockRequests, true)
            );

            Authentication::unlockUsersForGroups($unlockRequests);
        } catch (\Pcsg\GroupPasswordManager\Exception\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'message.ajax.actors.groups.getUnlockList.error',
                    array(
                        'error' => $Exception->getMessage()
                    )
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_pcsg_grouppasswordmanager_ajax_actors_groups_unlockUsers'
            );

            QUI\System\Log::writeException($Exception);

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'message.general.error'
                )
            );

            return false;
        }

        return true;
    },
    array('unlockRequests'),
    'Permission::checkAdminUser'
);
