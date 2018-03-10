<?php

use \Sequry\Core\Security\Handler\CryptoActors;

/**
 * Delete password actor (user/group)
 *
 * @param integer $id - user or group id
 * @param string $type - "user" or "group"
 * @return bool - success
 */
function package_sequry_core_ajax_actors_delete($id, $type)
{
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
    'package_sequry_core_ajax_actors_delete',
    array('id', 'type'),
    'Permission::checkAdminUser'
);
