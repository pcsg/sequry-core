<?php

use Sequry\Core\Security\Handler\CryptoActors;

/**
 * Check if current session user in a CryptoGroup
 *
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_users_isInCryptoGroup',
    function ($groupId) {
        return CryptoActors::getCryptoUser()->isInGroup((int)$groupId);
    },
    array('groupId'),
    'Permission::checkAdminUser'
);
