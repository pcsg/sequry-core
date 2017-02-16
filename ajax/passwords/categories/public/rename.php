<?php

use Pcsg\GroupPasswordManager\Handler\Categories;
use QUI\Utils\Security\Orthos;

/**
 * Rename password category
 *
 * @param int $id - category ID
 * @paremt string $title - new category title
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_passwords_categories_public_rename',
    function ($id, $title) {
        $title = Orthos::clear($title);

        try {
            Categories::renamePublic((int)$id, $title);
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'message.ajax.passwords.categories.renamePublic.error',
                    array(
                        'error' => $Exception->getMessage()
                    )
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_pcsg_grouppasswordmanager_ajax_passwords_categories_public_rename'
                . ' -> ' . $Exception->getMessage()
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
                'message.ajax.passwords.categories.renamePublic.success'
            )
        );

        return true;
    },
    array('id', 'title'),
    'Permission::checkAdminUser'
);
