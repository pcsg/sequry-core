<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\CryptoUser
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Security\Authentication\Plugin;
use Pcsg\GroupPasswordManager\Security\Keys\AuthKeyPair;
use Pcsg\GroupPasswordManager\Security\Keys\Key;
use Pcsg\GroupPasswordManager\Security\Keys\KeyPair;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use QUI;
use Pcsg\GroupPasswordManager\Constants\Tables;

/**
 * User Class
 *
 * Represents a password manager User that can retrieve encrypted passwords
 * if the necessary permission are given.
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class CryptoUser extends QUI\Users\User
{
    /**
     * CryptoUser constructor.
     *
     * @param integer $userId - quiqqer user id
     */
    public function __construct($userId)
    {
        $UserManager = new QUI\Users\Manager();
        parent::__construct($userId, $UserManager);
    }

    /**
     * Return Key pair for specific authentication plugin
     *
     * @param Plugin $AuthPlugin
     * @return AuthKeyPair
     * @throws QUI\Exception
     */
    public function getAuthKeyPair($AuthPlugin)
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => Tables::KEYPAIRS,
            'where'  => array(
                'authPluginId' => $AuthPlugin->getId()
            ),
            'limit'  => 1
        ));

        if (empty($result)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptouser.authkeypair.not.found',
                array(
                    'userId'       => $this->getId(),
                    'authPluginId' => $AuthPlugin->getId()
                )
            ));
        }

        $data = current($result);

        return new AuthKeyPair($data['id']);
    }

    /**
     * Gets titles and descriptions to all passwords the user has access to
     *
     * @param array $searchParams - search options
     * @return array
     */
    public function getPasswords($searchParams)
    {
        $passwords = array();

        // fetch all password ids the user has access to
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'dataId'
            ),
            'from'   => Tables::USER_TO_PASSWORDS,
            'where'  => array(
                'userId' => $this->getId()
            )
        ));

        $passwordIds = array();

        foreach ($result as $row) {
            $passwordIds[$row['dataId']] = true;
        }

        $passwordIds = array_keys($passwordIds);
        $Grid        = new \QUI\Utils\Grid($searchParams);

        // check if passwords found for this user - if not return empty list
        if (empty($passwordIds)) {
            return $Grid->parseResult($passwords, 0);
        }

        $params = $Grid->parseDBParams($searchParams);

        $params['select'] = array(
            'id',
            'title',
            'description',
            'securityClassId'
        );
        $params['from']   = Tables::PASSWORDS;

        // if frontend did not send "perPage" attribute -> assume no limit is wanted
        if (!isset($gridParams['perPage']) || empty($gridParams['perPage'])) {
            if (isset($params['limit'])) {
                unset($params['limit']);
            }
        }

        if (isset($gridParams['sortOn']) &&
            !empty($gridParams['sortOn'])
        ) {
            $params['order'] = $gridParams['sortOn'];

            if (isset($gridParams['sortBy']) &&
                !empty($gridParams['sortBy'])
            ) {
                $params['order'] .= ' ' . $gridParams['sortBy'];
            }
        }

        $params['where'] = array(
            'id' => array(
                'type'  => 'IN',
                'value' => $passwordIds
            )
        );

        if (isset($gridParams['search']) &&
            !empty($gridParams['search'])
        ) {
            $params['where']['title'] = array(
                'value' => $gridParams['search'],
                'type'  => '%LIKE%'
            );

            // @todo Suche nach Beschreibung
        }

        // fetch information for all corresponding passwords
        $result = QUI::getDataBase()->fetch($params);

        foreach ($result as $row) {
            $passwords[] = $row;
        }

        $params['count'] = 1;

        $count = QUI::getDataBase()->fetch($params);

        $result = $Grid->parseResult(
            $passwords,
            current(current($count))
        );

        return $result;
    }
}