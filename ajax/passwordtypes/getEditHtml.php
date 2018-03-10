<?php

use Sequry\Core\PasswordTypes\Handler;

/**
 * Get edit template
 *
 * @param string $type - password type
 * @return string - html template
 *
 * @throws QUI\Exception
 */
function package_sequry_core_ajax_passwordtypes_getEditHtml($type)
{
    return Handler::getEditTemplate($type);
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_passwordtypes_getEditHtml',
    array('type'),
    'Permission::checkAdminUser'
);
