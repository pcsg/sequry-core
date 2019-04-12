<?php


use Sequry\Core\Exception\Exception;
use Sequry\Core\Security\ActionAuthenticator;

/**
 * Authenticate for one or more authentication plugins to perform a specific single
 * system action.
 *
 * @param string $actionKey - Action identifier
 * @param array $authPluginIds - IDs for all authentication plugins
 * @param array $authData - authentication information for all authentication plugin
 * @return bool - true if correct, false if not correct
 *
 * @throws \Sequry\Core\Exception\Exception
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_action_authenticate',
    function ($actionKey, $authPluginIds, $authData) {
        $authData = json_decode($authData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception([
                'sequry/core',
                'exception.ajax.authenticate.decode.error'
            ]);
        }

        $authPluginIds = json_decode($authPluginIds, true);

        ActionAuthenticator::authenticate($actionKey, $authPluginIds, $authData);
    },
    ['actionKey', 'authPluginIds', 'authData'],
    'Permission::checkUser'
);
