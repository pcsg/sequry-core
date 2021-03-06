<?php

use Sequry\Core\Handler\Categories;

/**
 * Delete password category
 *
 * @param int $id - category ID
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_categories_private_delete',
    function ($id) {
        try {
            Categories::deletePrivate((int)$id);
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.passwords.categories.private.delete.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_passwords_categories_private_delete'
                .' -> '.$Exception->getMessage()
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
                'message.ajax.passwords.categories.private.delete.success'
            )
        );

        return true;
    },
    ['id'],
    'Permission::checkUser'
);
