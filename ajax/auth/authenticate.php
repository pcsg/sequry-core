<?php


use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Exception\Exception;
use Sequry\Core\Security\HiddenString;

/**
 * Authenticate for a security class
 *
 * @param integer $securityClassId - security class ID
 * @param string $authData - authentication information
 * @return bool - true if correct, false if not correct
 *
 * @throws \Sequry\Core\Exception\Exception
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_authenticate',
    function ($securityClassId, $authData) {
        $authData = json_decode($authData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception([
                'sequry/core',
                'exception.ajax.authenticate.decode.error'
            ]);
        }

        $sessioncache = !empty($authData['sessioncache']);

        foreach ($authData as $authPluginId => $authInfo) {
            if (!is_string($authInfo) || empty($authInfo)) {
                unset($authData[$authPluginId]);
                continue;
            }

            $authData[$authPluginId] = new HiddenString($authInfo);
        }

        if ($sessioncache) {
            $authData['sessioncache'] = true;
        }

        try {
            $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);
            $SecurityClass->authenticate($authData);
        } catch (QUI\Exception $Exception) {
            throw $Exception;
        } catch (\Exception $Exception) {
            return false;
        }

        return true;
    },
    ['securityClassId', 'authData'],
    'Permission::checkUser'
);
