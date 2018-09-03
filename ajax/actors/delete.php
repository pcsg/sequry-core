<?php

use \Sequry\Core\Security\Handler\CryptoActors;

/**
 * Delete password actor (user/group)
 *
 * @param integer $id - user or group id
 * @param string $type - "user" or "group"
 * @return bool - success
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_delete',
    function ($id, $type) {
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
    },
    ['id', 'type'],
    'Permission::checkAdminUser'
);
