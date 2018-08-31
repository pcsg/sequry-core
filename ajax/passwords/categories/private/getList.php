<?php

use Sequry\Core\Handler\Categories;

/**
 * Get list of private password categories
 *
 * @return array - public password categories
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_categories_private_getList',
    function () {
        return Categories::getPrivateList();
    },
    [],
    'Permission::checkUser'
);
