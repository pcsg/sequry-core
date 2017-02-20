<?php

use Pcsg\GroupPasswordManager\Handler\Categories;
use QUI\Utils\Security\Orthos;

/**
 * Get information of public category
 *
 * @param int $id - category ID
 * @return array - public password categories
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_passwords_categories_private_get',
    function ($ids) {
        $ids = Orthos::clearArray(json_decode($ids, true));
        return Categories::getPrivate($ids);
    },
    array('ids'),
    'Permission::checkAdminUser'
);