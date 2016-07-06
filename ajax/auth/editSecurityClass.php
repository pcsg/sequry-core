<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use QUI\Utils\Security\Orthos;

/**
 * Edit a security class
 *
 * @param integer $id - security class id
 * @param array $data - edit data
 * @return array
 */
function package_pcsg_grouppasswordmanager_ajax_auth_editSecurityClass($id, $data)
{
    $SecurityClass = Authentication::getSecurityClass((int)$id);

    return $SecurityClass->edit(
        Orthos::clearArray(
            json_decode($data, true)
        )
    );
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_auth_editSecurityClass',
    array('id', 'data'),
    'Permission::checkAdminUser'
);