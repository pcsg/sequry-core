<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use QUI\Utils\Security\Orthos;

/**
 * Set public categories to multiple passwords
 *
 * @param array $passwordIds
 * @param array $categoryIds
 * @param array $authData - Authentication data (for all passwords!)
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_passwords_categories_public_setToPasswords',
    function ($passwordIds, $categoryIds, $authData) {
        // Authentication
        $authData = json_decode($authData, true); // @todo ggf. filtern

        foreach ($authData as $securityClassId => $securityClassAuthData) {
            try {
                $SecurityClass = Authentication::getSecurityClass($securityClassId);
                $SecurityClass->authenticate($securityClassAuthData);
            } catch (\Exception $Exception) {
                QUI::getMessagesHandler()->addError(
                    QUI::getLocale()->get(
                        'pcsg/grouppasswordmanager',
                        'error.authentication.incorrect.auth.data'
                    )
                );

                return false;
            }
        }

        $passwordIds = Orthos::clearArray(json_decode($passwordIds, true));
        $categoryIds = Orthos::clearArray(json_decode($categoryIds, true));

        try {
            foreach ($passwordIds as $pwId) {
                try {
                    $Password = Passwords::get((int)$pwId);
                    $Password->setData(array(
                        'categoryIds' => $categoryIds
                    ));
                } catch (QUI\Exception $Exception) {
                    // nothing, just continue
                }
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_pcsg_grouppasswordmanager_ajax_passwords_categories_public_setToPasswords -> '
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
                'message.ajax.passwords.categories.public.setToPasswords.success'
            )
        );

        return true;
    },
    array('passwordIds', 'categoryIds', 'authData'),
    'Permission::checkAdminUser'
);
