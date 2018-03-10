<?php

use \Sequry\Core\Security\Handler\Authentication;

/**
 * Checks the auth status for every authentication plugin necessary
 * to authenticate for one or more SecurityClasses
 *
 * @param array $securityClassId - ids of SecurityClasses
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_auth_checkSecurityClassAuthStatus',
    function ($securityClassIds) {
        $authStatus = array(
            'authenticatedAll' => false
        );

        $securityClassIds = json_decode($securityClassIds, true);
        $authCounter      = 0;

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $authStatus;
        }

        foreach ($securityClassIds as $id) {
            $id              = (int)$id;
            $authStatus[$id] = Authentication::getSecurityClass($id)->getAuthStatus();

            if ($authStatus[$id]['authenticated']) {
                $authCounter++;
            }
        }

        if ($authCounter === count($securityClassIds)) {
            $authStatus['authenticatedAll'] = true;
        }

        return $authStatus;
    },
    array('securityClassIds'),
    'Permission::checkAdminUser'
);
