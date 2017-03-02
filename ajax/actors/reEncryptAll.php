<?php

use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Re-encrypt all keys a user has access to
 *
 * @param array $authData - authentication data
 * @return false|integer - false on error, Password ID on success
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_actors_reEncryptAll',
    function ($authData) {
        try {
            // authenticate
            $authData       = json_decode($authData, true);
            $CryptoUser     = CryptoActors::getCryptoUser();
            $authKeyPairIds = $CryptoUser->getAuthKeyPairIds();

            /** @var \Pcsg\GroupPasswordManager\Security\Keys\AuthKeyPair $AuthKeyPair */
            foreach ($authKeyPairIds as $authKeyPairId) {
                $AuthKeyPair = Authentication::getAuthKeyPair($authKeyPairId);
                $AuthPlugin  = $AuthKeyPair->getAuthPlugin();

                $pluginAuthData = $authData[$AuthPlugin->getId()];

                if (empty($pluginAuthData)) {
//                    throw new QUI\Exception(array(
//                        'pcsg/grouppasswordmanager',
//                        'exception.ajax.reEncryptAll.missing.auth.info'
//                    ));
                    continue;
                }

                try {
                    $AuthPlugin->authenticate($authData[$AuthPlugin->getId()], $CryptoUser);
                } catch (\Exception $Exception) {
                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.securityclass.authenticate.wrong.authdata',
                        array(
                            'authPluginId'    => $AuthPlugin->getId(),
                            'authPluginTitle' => $AuthPlugin->getAttribute('title')
                        )
                    ));
                }
            }

            $CryptoUser->reEncryptAllKeys();
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'message.ajax.reEncryptAll.error',
                    array(
                        'error' => $Exception->getMessage()
                    )
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_pcsg_grouppasswordmanager_ajax_actors_reEncryptAll -> '
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
                'message.ajax.reEncryptAll.success'
            )
        );

        return true;
    },
    array('authData'),
    'Permission::checkAdminUser'
);
