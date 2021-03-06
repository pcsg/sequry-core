<?php

use Sequry\Core\Handler\Categories;
use Sequry\Core\Security\Handler\Passwords;
use QUI\Utils\Security\Orthos;

/**
 * Set private categories to multiple passwords
 *
 * @param array $passwordIds
 * @param array $categoryIds
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_categories_private_setToPasswords',
    function ($passwordIds, $categoryIds) {
        $passwordIds = Orthos::clearArray(json_decode($passwordIds, true));
        $categoryIds = Orthos::clearArray(json_decode($categoryIds, true));

        try {
            foreach ($passwordIds as $pwId) {
                try {
                    $Password = Passwords::get((int)$pwId);
                    Categories::addPasswordToPrivateCategories($Password, $categoryIds);
                } catch (QUI\Exception $Exception) {
                    // nothing, just continue
                }
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_passwords_categories_private_setToPasswords -> '
                .$Exception->getMessage()
            );

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.general.error'
                )
            );

            return false;
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'sequry/core',
                'message.ajax.passwords.categories.private.setToPasswords.success'
            )
        );

        return true;
    },
    ['passwordIds', 'categoryIds'],
    'Permission::checkUser'
);
