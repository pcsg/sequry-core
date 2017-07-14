<?php

use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use QUI\Utils\Security\Orthos;
use QUI\Utils\Grid;

/**
 * Search password manager users
 *
 * @param string $searchParams - search parameters
 * @return array
 */
\QUI::$Ajax->registerFunction(
    'package_pcsg_grouppasswordmanager_ajax_actors_search',
    function ($searchParams) {
        $searchParams = Orthos::clearArray(json_decode($searchParams, true));
        $Grid         = new Grid($searchParams);

        return $Grid->parseResult(
            CryptoActors::searchActors($searchParams),
            CryptoActors::searchActors($searchParams, true)
        );
    },
    array('searchParams'),
    'Permission::checkAdminUser'
);
