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
function package_pcsg_grouppasswordmanager_ajax_passwordtypes_getViewHtml($type)
{
    if (!is_string($type)) {
        return '';
    }

    $type         = Orthos::clear($type);
    $dir          = dirname(dirname(dirname(__FILE__))) . '/bin/passwordtypes/';
    $templateFile = $dir . $type . '/View.html';

    if (!file_exists($templateFile)) {
        throw new QUI\Exception(array(
            'pcsg/grouppasswordmanager',
            'exception.passwordtypes.template.not.found'
        ), 404);
    }

    return file_get_contents($templateFile);
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwordtypes_getViewHtml',
    array('type'),
    'Permission::checkAdminUser'
);
