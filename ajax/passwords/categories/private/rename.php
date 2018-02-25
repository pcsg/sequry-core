<?php

use Sequry\Core\Handler\Categories;
use QUI\Utils\Security\Orthos;

/**
 * Rename password category
 *
 * @param int $id - category ID
 * @paremt string $title - new category title
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_categories_private_rename',
    function ($id, $title) {
        $title = Orthos::clear($title);

        try {
            Categories::renamePrivate((int)$id, $title);
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.passwords.categories.private.rename.error',
                    array(
                        'error' => $Exception->getMessage()
                    )
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_passwords_categories_private_rename'
                . ' -> ' . $Exception->getMessage()
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
                'message.ajax.passwords.categories.private.rename.success'
            )
        );

        return true;
    },
    array('id', 'title'),
    'Permission::checkAdminUser'
);
