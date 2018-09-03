<?php

use QUI\Utils\Security\Orthos;
use Sequry\Core\Handler\Categories;

/**
 * Create new password category
 *
 * @param string $title - new category title
 * @param int $parentId (optional) - parent id
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_categories_private_create',
    function ($title, $parentId = null) {
        $title = Orthos::clear($title);

        if ($parentId) {
            $parentId = (int)$parentId;
        }

        try {
            Categories::createPrivate($title, $parentId);
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.passwords.categories.private.create.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_passwords_categories_private_create'
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
                'message.ajax.passwords.categories.private.create.success',
                [
                    'title' => $title
                ]
            )
        );

        return true;
    },
    ['title', 'parentId'],
    'Permission::checkUser'
);
