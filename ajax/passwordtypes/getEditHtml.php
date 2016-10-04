<?php

use Pcsg\GroupPasswordManager\PasswordTypes\Handler;

/**
 * Get edit template
 *
 * @param string $type - password type
 * @return string - html template
 *
 * @throws QUI\Exception
 */
function package_pcsg_grouppasswordmanager_ajax_passwordtypes_getEditHtml($type)
{
    ini_set('display_errors', 1);

    return Handler::getEditTemplate($type);
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwordtypes_getEditHtml',
    array('type'),
    'Permission::checkAdminUser'
);
