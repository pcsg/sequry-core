<?php

use \Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

/**
 * Delete password actor (user/group)
 *
 * @param integer $id - user or group id
 * @param string $type - "user" or "group"
 * @return bool - success
 */
function package_pcsg_grouppasswordmanager_ajax_actors_delete($id, $type)
{
    ini_set('display_errors', 1);

    switch ($type) {
        case 'user':
            $Actor = CryptoActors::getCryptoUser((int)$id);
            $Actor->delete();
            break;

        case 'group':
            $Actor = CryptoActors::getCryptoGroup((int)$id);
            $Actor->delete();
            break;
    }

    return true;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_actors_delete',
    array('id', 'type'),
    'Permission::checkAdminUser'
);
