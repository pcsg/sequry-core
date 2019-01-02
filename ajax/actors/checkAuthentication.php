<?php

/**
 * Checks if the current user is authenticated (has a session)
 *
 * @return void
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_actors_checkAuthentication',
    function () {
        // @todo this is just a dummy function
    },
    [],
    'Permission::checkUser'
);
