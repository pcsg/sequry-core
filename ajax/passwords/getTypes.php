<?php

/**
 * Get available password types
 *
 * @return array - password types
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getTypes()
{
    $types = array();
    $dir   = dirname(dirname(dirname(__FILE__))) . '/bin/controls/passwordtypes';
    $files = \QUI\Utils\System\File::readDir($dir, true);

    foreach ($files as $file) {
        if (mb_strpos($file, '.js') === false) {
            continue;
        }

        switch ($file) {
            case 'Content.js':
            case 'Select.js':
                continue 2;
                break;
        }

        $types[] = basename($file, '.js');
    }

    return $types;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_getTypes',
    array(),
    'Permission::checkAdminUser'
);
