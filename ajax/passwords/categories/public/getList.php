<?php

use Sequry\Core\Handler\Categories;

/**
 * Get list of public password categories
 *
 * @return array - public password categories
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_categories_public_getList',
    function () {
        return Categories::getPublicList();
    },
    array(),
    'Permission::checkAdminUser'
);
