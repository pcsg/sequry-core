<?php

/**
 * Get password actor info (user/group)
 *
 * @param integer $id - user or group id
 * @param string $type - "user" or "group"
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_auth_getActor($id, $type)
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
            $info  = array(
                'id'   => $Actor->getId(),
                'name' => $Actor->getAttribute('name')
            );
            break;
    }

    return $info;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_getActor',
    array('id', 'type'),
    'Permission::checkAdminUser'
);