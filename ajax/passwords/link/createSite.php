<?php

use Sequry\Core\Security\Handler\PasswordLinks;

/**
 * Create a PasswordLink Site
 *
 * @return bool - success
 * @throws QUI\Exception
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_link_createSite',
    function ($project) {
        try {
            $Project = QUI::getProjectManager()->decode($project);
            PasswordLinks::createPasswordLinkSite($Project, 1);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.passwords.link.createSite.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_passwords_link_createSite'
            );

            QUI\System\Log::writeException($Exception);

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
                'message.passwords.link.createSite.success'
            )
        );

        return true;
    },
    ['project'],
    'Permission::checkAdminUser'
);
