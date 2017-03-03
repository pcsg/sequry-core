<?php

use Pcsg\GroupPasswordManager\PasswordTypes\Handler;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use QUI\Utils\Security\Orthos;

/**
 * Get a single password object
 *
 * @param integer $passwordId - the id of the password object
 * @param array $authData - authentication information
 * @return string|false - view html; false on error
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getView($passwordId, $authData)
{
    $passwordId = (int)$passwordId;

    try {
        // authenticate
        Passwords::getSecurityClass(
            $passwordId
        )->authenticate(
            json_decode($authData, true) // @todo diese daten ggf. filtern
        );

        $Password = Passwords::get($passwordId);
        $viewData = $Password->getViewData();

        foreach ($viewData as $k => $v) {
            if (is_string($v)) {
                $viewData[$k] = Orthos::escapeHTML($v);
            }
        }

        return Handler::getViewHtml($Password->getDataType(), $viewData);
    } catch (QUI\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'message.ajax.passwords.getView.error',
                array(
                    'error'      => $Exception->getMessage(),
                    'passwordId' => $passwordId
                )
            )
        );

        return false;
    } catch (\Exception $Exception) {
        QUI\System\Log::addError(
            'AJAX :: package_pcsg_grouppasswordmanager_ajax_passwords_getView -> ' . $Exception->getMessage()
        );

        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'message.general.error'
            )
        );

        return false;
    }
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_getView',
    array('passwordId', 'authData'),
    'Permission::checkAdminUser'
);
