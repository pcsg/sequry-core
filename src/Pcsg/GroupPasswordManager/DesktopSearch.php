<?php

namespace Pcsg\GroupPasswordManager;

use QUI;
use QUI\BackendSearch\ProviderInterface;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

class DesktopSearch implements ProviderInterface
{
    const GROUP_PASSWORDS = 'passwords';

    /**
     * Build the cache
     *
     * @return mixed
     */
    public function buildCache()
    {
    }

    /**
     * Execute a search
     *
     * @param string $search
     * @param array $params
     * @return mixed
     */
    public function search($search, $params = array())
    {
        if (!isset($params['filterGroups'])
            || !in_array(self::GROUP_PASSWORDS, $params['filterGroups'])
        ) {
            return array();
        }

        $CryptoActor = CryptoActors::getCryptoUser(); // session user

        $result = $CryptoActor->getPasswordList(array(
            'search' => array(
                'searchterm' => $search
            )
        ));

        $searchResults = array();

        $groupLabel = QUI::getLocale()->get(
            'pcsg/grouppasswordmanager',
            'desktopsearch.group.passwords.label'
        );

        foreach ($result as $password) {
            $searchResults[] = array(
                'id'          => $password['id'],
                'title'       => $password['title'],
                'description' => $password['description'],
                'icon'        => 'fa fa-diamond',
                'group'       => self::GROUP_PASSWORDS,
                'groupLabel'  => $groupLabel
            );
        }

        return $searchResults;
    }

    /**
     * Return a search entry
     *
     * @param integer $id
     * @return mixed
     */
    public function getEntry($id)
    {
        return array(
            'searchdata' => json_encode(array(
                'require' => 'package/pcsg/grouppasswordmanager/bin/controls/passwords/DesktopSearchResult',
                'params'  => array(
                    'passwordId' => (int)$id
                )
            ))
        );
    }

    /**
     * Get all available search groups of this provider.
     * Search results can be filtered by these search groups.
     *
     * @return array
     */
    public function getFilterGroups()
    {
        return array(
            array(
                'group' => self::GROUP_PASSWORDS,
                'label' => array(
                    'pcsg/grouppasswordmanager',
                    'desktopsearch.group.passwords.label'
                )
            )
        );
    }
}
