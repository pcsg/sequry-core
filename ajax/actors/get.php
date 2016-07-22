<?php

use \Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

/**
 * Get password actor info (user/group)
 *
 * @param integer $id - user or group id
 * @param string $type - "user" or "group"
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_actors_get($id, $type)
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
            $Actor = QUI::getGroups()->get((int)$id);

            $result = QUI::getDataBase()->fetch(array(
                'count' => 1,
                'from'  => \Pcsg\GroupPasswordManager\Constants\Tables::KEYPAIRS_GROUP,
                'where' => array(
                    'groupId' => $Actor->getId()
                )
            ));

            $securityClassId = false;

            if (current(current($result)) > 0) {
                $CryptoGroup     = CryptoActors::getCryptoGroup((int)$id);
                $securityClassId = $CryptoGroup->getSecurityClassId();
            }

            $info = array(
                'id'                 => $Actor->getId(),
                'name'               => $Actor->getAttribute('name'),
                'securityClassId'    => $securityClassId,
                'sessionUserInGroup' => QUI::getUserBySession()->isInGroup($Actor->getId())
            );
            break;
    }

    return $info;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_actors_get',
    array('id', 'type'),
    'Permission::checkAdminUser'
);