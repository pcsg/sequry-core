<?php

use Sequry\Core\Handler\Categories;
use Sequry\Core\Security\Handler\Passwords;
use QUI\Utils\Security\Orthos;

/**
 * Set private categories to a password
 *
 * @param int $passwordId
 * @param array $categoryIds
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_categories_private_setToPassword',
    function ($passwordId, $categoryIds) {
        $categoryIds = Orthos::clearArray(json_decode($categoryIds, true));

        try {
            $Password = Passwords::get((int)$passwordId);
            Categories::addPasswordToPrivateCategories($Password, $categoryIds);
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.passwords.categories.private.setToPassword.error',
                    array(
                        'error'      => $Exception->getMessage(),
                        'passwordId' => $passwordId
                    )
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_passwords_categories_private_setToPassword -> '
                . $Exception->getMessage()
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
                'message.ajax.passwords.categories.private.setToPassword.success',
                array(
                    'passwordId' => $passwordId
                )
            )
        );

        return true;
    },
    array('passwordId', 'categoryIds'),
    'Permission::checkAdminUser'
);
