<?php

use Sequry\Core\Handler\Categories;
use QUI\Utils\Security\Orthos;

/**
 * Get information of public category/categories
 *
 * @param array $id - category IDs
 * @return array - public password categories
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_categories_public_get',
    function ($ids) {
        $ids = Orthos::clearArray(json_decode($ids, true));
        return Categories::getPublic($ids);
    },
    ['ids'],
    'Permission::checkUser'
);
