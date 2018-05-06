<?php

use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Constants\Tables;
use Sequry\Core\Security\Handler\Authentication;

/**
 * Get password actor info (user/group)
 *
 * @param integer $id - user or group id
 * @param string $type - "user" or "group"
 * @return array
 *
 * @throws \Sequry\Core\Exception\Exception
 * @throws QUI\Users\Exception
 * @throws QUI\Exception
 */
function package_sequry_core_ajax_actors_get($id, $type)
{
    $info = array();

    switch ($type) {
        case 'user':
            $Actor = QUI::getUsers()->get((int)$id);
            $info  = array(
                'id'   => $Actor->getId(),
                'name' => $Actor->getName()
            );
            break;

        case 'group':
            $CryptoGroup = CryptoActors::getCryptoGroup((int)$id);

            $result = QUI::getDataBase()->fetch(array(
                'count' => 1,
                'from'  => Tables::keyPairsGroup(),
                'where' => array(
                    'groupId' => $CryptoGroup->getId()
                )
            ));

            $securityClassIds             = array();
            $eligibleUserForSecurityClass = array();
            $groupAdminUserIds            = $CryptoGroup->getAdminUserIds();

            if (current(current($result)) > 0) {
                $securityClassIds = $CryptoGroup->getSecurityClassIds();
            }

            /*
             * Check which SecurityClasses can be added to the groups
             *
             * This is the case if all Group Administrators are eligible
             * for the respective SecurityClass
             */
            foreach (Authentication::getSecurityClassesList() as $secClassId => $data) {
                if (in_array($secClassId, $securityClassIds)) {
                    $eligibleUserForSecurityClass[$secClassId] = true;
                    continue;
                }

                $SecurityClass            = Authentication::getSecurityClass($secClassId);
                $eligibleUserIds          = $SecurityClass->getEligibleUserIds();
                $eligibleUserIdsIntersect = array_intersect(
                    $groupAdminUserIds,
                    $eligibleUserIds
                );

                $eligibleUserForSecurityClass[$secClassId] = count($groupAdminUserIds) === count($eligibleUserIdsIntersect);
            }

            $info = array(
                'id'                       => $CryptoGroup->getId(),
                'name'                     => $CryptoGroup->getAttribute('name'),
                'securityClassIds'         => $securityClassIds,
                'sessionUserInGroup'       => QUI::getUserBySession()->isInGroup($CryptoGroup->getId()),
                'securityClassEligibility' => $eligibleUserForSecurityClass,
                'groupAdminUserIds'        => $groupAdminUserIds
            );
            break;
    }

    return $info;
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_actors_get',
    array('id', 'type'),
    'Permission::checkAdminUser'
);
