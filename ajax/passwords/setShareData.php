<?php

use Sequry\Core\Security\Handler\Passwords;
use QUI\Utils\Security\Orthos;
use Sequry\Core\Password;

/**
 * Set share data from password object
 *
 * @param integer $passwordId - ID of password
 * @param string $shareData - share users and groups
 * @return array|false - password data or false on error
 */
function package_sequry_core_ajax_passwords_setShareData($passwordId, $shareData)
{
    $passwordId = (int)$passwordId;
    $Password   = Passwords::get($passwordId);
    $shareData  = Orthos::clearArray(json_decode($shareData, true));

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
                'sequry/core',
                'success.password.share',
                array(
                    'passwordId' => $passwordId
                )
            )
        );
    } catch (QUI\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'sequry/core',
                'error.password.share',
                array(
                    'passwordId' => $passwordId,
                    'error'      => $Exception->getMessage()
                )
            )
        );

        return false;
    } catch (\Exception $Exception) {
        QUI\System\Log::addError(
            'AJAX :: package_sequry_core_ajax_passwords_setShareData -> '
            . $Exception->getMessage()
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

    // get password data
    return $Password->getShareData();
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_passwords_setShareData',
    array('passwordId', 'shareData'),
    'Permission::checkAdminUser'
);
