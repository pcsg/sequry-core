<?php

use Sequry\Core\Handler\Categories;
use QUI\Utils\Security\Orthos;

/**
 * Get information of public category
 *
 * @param int $id - category ID
 * @return array - public password categories
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_categories_private_get',
    function ($ids) {
        $ids = Orthos::clearArray(json_decode($ids, true));
        return Categories::getPrivate($ids);
    },
    ['ids'],
    'Permission::checkUser'
);
