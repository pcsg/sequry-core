<?php

/**
 * Generate random PIN for password link
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_link_generatePin',
    function () {
        $pin = [];

        for ($i = 0; $i < 6; $i++) {
            $pin[] = random_int(0, 9);
        }

        shuffle($pin);

        return implode('', $pin);
    },
    [],
    'Permission::checkUser'
);
