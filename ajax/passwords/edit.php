<?php

use Sequry\Core\Handler\Categories;
use Sequry\Core\Security\Handler\Passwords;
use Sequry\Core\Security\Handler\CryptoActors;

/**
 * Edit a password object
 *
 * @param integer $passwordId - ID of password
 * @param string $passwordData - edited data of password
 * @return false|array - new pasword data; false if data could not be retrieved
 *
 * @throws QUI\Exception
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_edit',
    function ($passwordId, $passwordData) {
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
                            'sequry/core',
                            'message.passwords.edit.private.categories.no.access',
                            [
                                'passwordId' => $passwordId
                            ]
                        )
                    );
                } else {
                    Categories::addPasswordToPrivateCategories($Password, $passwordData['categoryIdsPrivate']);
                }
            }

            QUI::getMessagesHandler()->addSuccess(
                QUI::getLocale()->get(
                    'sequry/core',
                    'success.password.edit',
                    [
                        'passwordId' => $passwordId
                    ]
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
                    'sequry/core',
                    'error.password.edit',
                    [
                        'error'      => $Exception->getMessage(),
                        'passwordId' => $passwordId
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_sequry_core_ajax_passwords_edit -> '
                .$Exception->getMessage()
            );

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.general.error'
                )
            );

            return false;
        }
    },
    ['passwordId', 'passwordData'],
    'Permission::checkUser'
);
