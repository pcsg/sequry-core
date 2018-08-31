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
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwordtypes_getEditHtml',
    function ($type) {
        return Handler::getEditTemplate($type);
    },
    ['type'],
    'Permission::checkUser'
);
