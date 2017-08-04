<?php

use Pcsg\GroupPasswordManager\Handler\Categories;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

/**
 * Edit a password object
 *
 * @param integer $passwordId - ID of password
 * @param string $passwordData - edited data of password
 * @return false|array - new pasword data; false if data could not be retrieved
 *
 * @throws QUI\Exception
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_edit($passwordId, $passwordData)
{
    $passwordId = (int)$passwordId;

    // edit password
    try {
        $Password     = Passwords::get($passwordId);
        $passwordData = json_decode($passwordData, true);
        $Password->setData($passwordData);

        if (isset($passwordData['categoryIdsPrivate'])
            && is_array($passwordData['categoryIdsPrivate'])
            && !empty($passwordData['categoryIdsPrivate'])
        ) {
            if (!$Password->hasPasswordAccess(CryptoActors::getCryptoUser())) {
                QUI::getMessagesHandler()->addAttention(
                    QUI::getLocale()->get(
                        'pcsg/grouppasswordmanager',
                        'message.passwords.edit.private.categories.no.access',
                        array(
                            'passwordId' => $passwordId
                        )
                    )
                );
            } else {
                Categories::addPasswordToPrivateCategories($Password, $passwordData['categoryIdsPrivate']);
            }
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'success.password.edit',
                array(
                    'passwordId' => $passwordId
                )
            )
        );

        // if owner changed during this edit process, data cannot be retrieved
        try {
            return $Password->getData();
        } catch (\Exception $Exception) {
            return false;
        }
    } catch (QUI\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'error.password.edit',
                array(
                    'error'      => $Exception->getMessage(),
                    'passwordId' => $passwordId
                )
            )
        );

        return false;
    } catch (\Exception $Exception) {
        QUI\System\Log::addError(
            'AJAX :: package_pcsg_grouppasswordmanager_ajax_passwords_edit -> '
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
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_edit',
    array('passwordId', 'passwordData'),
    'Permission::checkAdminUser'
);
