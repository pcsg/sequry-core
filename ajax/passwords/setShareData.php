<?php

use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use QUI\Utils\Security\Orthos;
use Pcsg\GroupPasswordManager\Password;

/**
 * Set share data from password object
 *
 * @param integer $passwordId - ID of password
 * @param array $shareData - share users and groups
 * @param array $authData - authentication information
 * @return array|false - password data or false on error
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_setShareData($passwordId, $shareData, $authData)
{
    $passwordId = (int)$passwordId;

    // authenticate
    Passwords::getSecurityClass(
        $passwordId
    )->authenticate(
        json_decode($authData, true) // @todo diese daten ggf. filtern
    );

    $Password  = Passwords::get($passwordId);
    $shareData = Orthos::clearArray(json_decode($shareData, true));

    foreach ($shareData as $k => $entry) {
        switch ($entry['type']) {
            case 'user':
                $entry['type'] = Password::OWNER_TYPE_USER;
                break;

            case 'group':
                $entry['type'] = Password::OWNER_TYPE_GROUP;
                break;
        }

        $shareData[$k] = $entry;
    }

    try {
        $Password->setShareData($shareData);

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'success.password.share',
                array(
                    'passwordId' => $passwordId
                )
            )
        );
    } catch (QUI\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'error.password.share',
                array(
                    'passwordId' => $passwordId,
                    'error'     => $Exception->getMessage()
                )
            )
        );

        return false;
    } catch (\Exception $Exception) {
        QUI\System\Log::addError(
            'AJAX :: package_pcsg_grouppasswordmanager_ajax_passwords_setShareData -> '
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

    // get password data
    return $Password->getShareData();
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_setShareData',
    array('passwordId', 'shareData', 'authData'),
    'Permission::checkAdminUser'
);
