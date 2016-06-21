<?php

/**
 * Get a single password object
 *
 * @param integer $passwordId - the id of the password object
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_get($passwordId)
{
    // @todo
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_get',
    array('passwordId'),
    'Permission::checkAdminUser'
);