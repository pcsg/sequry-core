<?php

use Pcsg\GroupPasswordManager\Handler\Categories;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use QUI\Utils\Security\Orthos;

/**
 * Set private categories to multiple passwords
 *
 * @param array $passwordIds
 * @param array $categoryIds
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_passwords_categories_private_setToPasswords',
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
                'AJAX :: package_pcsg_grouppasswordmanager_ajax_passwords_categories_private_setToPasswords -> '
                . $Exception->getMessage()
            );

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'message.general.error'
                )
            );

            return false;
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'message.ajax.passwords.categories.private.setToPasswords.success'
            )
        );

        return true;
    },
    array('passwordIds', 'categoryIds'),
    'Permission::checkAdminUser'
);