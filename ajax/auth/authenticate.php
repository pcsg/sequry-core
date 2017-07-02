<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Authenticate for a security class
 *
 * @param integer $securityClassId - security class ID
 * @param string $authData - authentication information
 * @return bool - true if correct, false if not correct
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_auth_authenticate',
    function ($securityClassId, $authData) {
        try {
            $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);
            $authData      = json_decode($authData, true);
            $SecurityClass->authenticate($authData);
        } catch (QUI\Exception $Exception) {
            throw $Exception;
        } catch (\Exception $Exception) {
            return false;
        }

        return true;
    },
    array('securityClassId', 'authData'),
    'Permission::checkAdminUser'
);
