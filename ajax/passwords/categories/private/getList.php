<?php

use Pcsg\GroupPasswordManager\Handler\Categories;

/**
 * Get list of private password categories
 *
 * @return array - public password categories
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_passwords_categories_private_getList',
    function () {
        return Categories::getPrivateList();
    },
    array(),
    'Permission::checkAdminUser'
);
