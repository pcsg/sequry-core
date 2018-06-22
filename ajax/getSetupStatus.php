<?php

use Sequry\Core\Security\Handler\Passwords;

/**
 * Checks the current Sequry setup status
 *
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_getSetupStatus',
    function () {
        return [
            'setupComplete'        => Passwords::isSetupComplete(),
            'setupWizardInstalled' => QUI::getPackageManager()->isInstalled('sequry/setup-wizard')
        ];
    },
    [],
    'Permission::checkAdminUser'
);
