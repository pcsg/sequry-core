<?php

namespace Pcsg\GroupPasswordManager;

use QUI\Workspace\Search\ProviderInterface;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;

class DesktopSearch implements ProviderInterface
{
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
        $CryptoActor = CryptoActors::getCryptoUser(); // session user

        $result = $CryptoActor->getPasswordList(array(
            'searchterm' => $search
        ));

        $searchResults = array();

        foreach ($result as $password) {
            $searchResults[] = array(
                'id'          => $password['id'],
                'title'       => $password['title'],
                'description' => $password['description'],
                'icon'        => 'fa fa-diamond',
                'searchtype'  => 'passwords'
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
                'require' => 'package/pcsg/grouppasswordmanager/bin/controls/passwords/SearchResultDisplay',
                'params'  => array(
                    'passwordId' => (int)$id
                )
            ))
        );
    }
}
