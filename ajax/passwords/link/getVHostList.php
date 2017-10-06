<?php

use Pcsg\GroupPasswordManager\PasswordLink;

/**
 * Get list of VHosts
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_passwords_link_getVHostList',
    function () {
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
            }
        }

        return $validVhosts;
    },
    false
);
