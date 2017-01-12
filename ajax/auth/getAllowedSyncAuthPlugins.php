<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Constants\Tables;

/**
 * Get IDs of authentication plugins that are allowed to be used for a sync operation
 *
 * @param integer $authPluginId - id of auth plugin
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_auth_getAllowedSyncAuthPlugins($authPluginId)
{
    $AuthPlugin                   = Authentication::getAuthPlugin((int)$authPluginId);
    $CryptoUser                   = CryptoActors::getCryptoUser();
    $limitedGroupAccessData       = $CryptoUser->getNonFullyAccessibleGroupAndSecurityClassIds($AuthPlugin);
    $allowedAuthPluginIdsPerGroup = array();

    foreach ($limitedGroupAccessData as $groupId => $limitedSecurityClassIds) {
        if (empty($limitedSecurityClassIds)) {
            continue;
        }

        $CryptoGroup = CryptoActors::getCryptoGroup($groupId);

        $allowedAuthPluginIdsPerGroup[$CryptoGroup->getId()] = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'groupId',
                'userKeyPairId'
            ),
            'from'   => Tables::USER_TO_GROUPS,
            'where'  => array(
                'groupId'         => $CryptoGroup->getId(),
                'userId'          => $CryptoUser->getId(),
                'securityClassId' => array(
                    'type'  => 'IN',
                    'value' => $limitedSecurityClassIds
                )
//                'userKeyPairId' => $AuthKeyPair->getId()
            )
        ));

        foreach ($result as $row) {
            $KeyPair = Authentication::getAuthKeyPair(
                $row['userKeyPairId']
            );

            $allowedAuthPluginIdsPerGroup[$CryptoGroup->getId()][] = $KeyPair->getAuthPlugin()->getId();
        }
    }

    $allowedAuthPluginIds = array_values($allowedAuthPluginIdsPerGroup);

    if (empty($allowedAuthPluginIds)) {
        return array();
    }

    if (count($allowedAuthPluginIds) === 1) {
        return $allowedAuthPluginIds[0];
    }

    return call_user_func_array('array_intersect', $allowedAuthPluginIds);
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_getAllowedSyncAuthPlugins',
    array('authPluginId'),
    'Permission::checkAdminUser'
);
