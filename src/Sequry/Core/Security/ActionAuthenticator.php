<?php

namespace Sequry\Core\Security;

use QUI;
use Sequry\Core\Exception\InvalidAuthDataException;
use Sequry\Core\Exception\PermissionDeniedException;
use Sequry\Core\Security\Handler\Authentication;

/**
 * Class ActionAuthenticator
 *
 * Handles authentication for a single action. This can be used to force authentication
 * with one or more specific authentication plugins to perform any action in the system.
 *
 * Does not save/cache any sensitive cache data, just saves if an authentication took place.
 */
class ActionAuthenticator
{
    /**
     * Check if user is authenticated for the given $actionKey with the given authentication plugins
     *
     * @param string $actionKey - Arbitrary key to identify the action
     * @param array $authPluginIds - IDs of all authentication plugins to authenticate for
     * @return void
     * @throws PermissionDeniedException
     */
    public static function checkActionAuthentication(string $actionKey, array $authPluginIds)
    {
        $sessionActionKey = 'action_authenticator_'.$actionKey;
        $sessionAction    = QUI::getSession()->get($sessionActionKey);

        if (empty($sessionAction)) {
            QUI::getAjax()->triggerGlobalJavaScriptCallback(
                'actionAuthentication',
                [
                    'authPluginIds' => $authPluginIds,
                    'actionKey'     => $actionKey
                ]
            );

            throw new PermissionDeniedException([
                'sequry/core',
                'exception.Security.ActionAuthenticator.authentication_required'
            ]);
        }

        // If user was authenticated with the given authentication plugins
        // delete actionKey from session
        QUI::getSession()->remove($sessionActionKey);
    }

    /**
     * Authenticate for a single action
     *
     * @param string $actionKey - Arbitrary key to identify the action
     * @param array $authPluginIds - IDs of all authentication plugins to authenticate for
     * @param array $authData - Authentication data
     * @return void
     * @throws InvalidAuthDataException
     */
    public static function authenticate(string $actionKey, array $authPluginIds, array $authData)
    {
        foreach ($authPluginIds as $authPluginId) {
            try {
                $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);

                throw new InvalidAuthDataException([
                    'exception.Security.ActionAuthenticator.authentication_required'
                ]);
            }

            if (empty($authData[$AuthPlugin->getId()])) {
                throw new InvalidAuthDataException([
                    'exception.Security.ActionAuthenticator.authentication_required'
                ]);
            }

            $AuthPlugin->authenticate(new HiddenString($authData[$AuthPlugin->getId()]));
        }

        // Successful authentication for $actionKey
        QUI::getSession()->set('action_authenticator_'.$actionKey, true);
    }
}
