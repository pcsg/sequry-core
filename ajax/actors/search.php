<?php

use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use QUI\Utils\Security\Orthos;

/**
 * Get password actor info (user/group)
 *
 * @param string $search - search term
 * @param string $type - "user" or "group"
 * @param integer $securityClassId - id of security class
 * @param integer $limit
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_actors_search',
    function ($search, $type, $securityClassId, $limit)
    {
        $SecurityClass = Authentication::getSecurityClass((int)$securityClassId);

        $search = Orthos::clear($search);
        $limit  = (int)$limit;

        return $SecurityClass->suggestSearchEligibleActors($search, $type, $limit);
    },
    array('search', 'type', 'securityClassId', 'limit'),
    'Permission::checkAdminUser'
);
