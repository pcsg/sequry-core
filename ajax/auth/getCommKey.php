<?php

use Sequry\Core\Security\Handler\Authentication;

/**
 * Get the symmetric key that is used for encryption
 * between frontend and backend for the current session
 *
 * @return false|string
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_getCommKey',
    function () {
        $key = Authentication::getSessionCommunicationKey();

        $key['key'] = array_values(unpack('C*', $key['key']));
        $key['iv']  = array_values(unpack('C*', $key['iv']));

        return $key;
    },
    [],
    'Permission::checkUser'
);
