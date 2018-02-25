<?php

use Pcsg\GroupPasswordManager\PasswordLink;
use Pcsg\GroupPasswordManager\Constants\Settings;

/**
 * Get list of VHosts
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_passwords_link_getVHostList',
    function () {
        // check if VHost selection is enabled
        $Conf = QUI::getPackage('pcsg/grouppasswordmanager')->getConfig();

        $VhostManager = new \QUI\System\VhostManager();
        $validVhosts  = array();

        foreach ($VhostManager->getList() as $vhost => $v) {
            $Project = $VhostManager->getProjectByHost($vhost);

            $sites = $Project->getSites(array(
                'where' => array(
                    'type' => PasswordLink::SITE_TYPE
                ),
                'limit' => 1
            ));

            if (!empty($sites)) {
                $validVhosts[] = $vhost;

                if (!$Conf->get('settings', Settings::SHOW_VHOST_LIST)) {
                    break;
                }
            }
        }

        return $validVhosts;
    },
    false
);
