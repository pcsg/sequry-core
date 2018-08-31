<?php

use Sequry\Core\PasswordLink;
use Sequry\Core\Constants\Settings;

/**
 * Get list of VHosts
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_link_getVHostList',
    function () {
        // check if VHost selection is enabled
        $Conf = QUI::getPackage('sequry/core')->getConfig();

        $VhostManager = new \QUI\System\VhostManager();
        $validVhosts  = [];

        foreach ($VhostManager->getList() as $vhost => $v) {
            $Project = $VhostManager->getProjectByHost($vhost);

            $sites = $Project->getSites([
                'where' => [
                    'type' => PasswordLink::SITE_TYPE
                ],
                'limit' => 1
            ]);

            if (!empty($sites)) {
                $validVhosts[] = $vhost;

                if (!$Conf->get('settings', Settings::SHOW_VHOST_LIST)) {
                    break;
                }
            }
        }

        return $validVhosts;
    },
    [],
    'Permission::checkUser'
);
