<?php

use Sequry\Core\Security\Handler\Passwords;

/**
 * Delete a password object
 *
 * @param integer $passwordId - ID of password
 * @return bool - success
 */
function package_sequry_core_ajax_passwords_delete($passwordId)
{
    $passwordId = (int)$passwordId;

    // delete password
    try {
        Passwords::get($passwordId)->delete();

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'sequry/core',
                'success.password.delete',
                array(
                    'passwordId' => $passwordId
                )
            )
        );
    } catch (QUI\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'sequry/core',
                'error.password.delete',
                array(
                    'passwordId' => $passwordId,
                    'error'      => $Exception->getMessage()
                )
            )
        );

        return false;
    } catch (\Exception $Exception) {
        QUI\System\Log::addError(
            'AJAX :: package_sequry_core_ajax_passwords_delete -> '
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

    return true;
}

\QUI::$Ajax->register(
    'package_sequry_core_ajax_passwords_delete',
    array('passwordId'),
    'Permission::checkAdminUser'
);
