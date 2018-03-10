<?php

use QUI\Utils\Security\Orthos;

/**
 * Get edit template
 *
 * @param string $type - password type
 * @return string - html template
 *
 * @throws QUI\Exception
 */
function package_sequry_core_ajax_passwordtypes_getViewHtml($type)
{
    if (!is_string($type)) {
        return '';
    }

    $type         = Orthos::clear($type);
    $dir          = dirname(dirname(dirname(__FILE__))) . '/bin/passwordtypes/';
    $templateFile = $dir . $type . '/View.html';

    if (!file_exists($templateFile)) {
        throw new QUI\Exception(array(
            'sequry/core',
            'exception.passwordtypes.template.not.found'
        ), 404);
    }

    return file_get_contents($templateFile);
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_passwordtypes_getViewHtml',
    array('type'),
    'Permission::checkAdminUser'
);
