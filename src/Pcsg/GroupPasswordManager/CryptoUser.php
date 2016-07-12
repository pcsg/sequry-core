<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\CryptoUser
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Security\Authentication\Plugin;
use Pcsg\GroupPasswordManager\Security\Keys\AuthKeyPair;
use QUI;
use Pcsg\GroupPasswordManager\Constants\Tables;
use QUI\Utils\Security\Orthos;

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
                'authPluginId' => $AuthPlugin->getId(),
                'userId'       => $this->getId()
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
     * @param bool $countOnly (optional) - get count only
     * @return array
     */
    public function getPasswords($searchParams, $countOnly = false)
    {
        $PDO       = QUI::getDataBase()->getPDO();
        $passwords = array();
        $binds     = array();
        $where     = array();

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
        $gridParams  = $Grid->parseDBParams($searchParams);

        // check if passwords found for this user - if not return empty list
        if (empty($passwordIds)) {
            return $Grid->parseResult($passwords, 0);
        }

        if ($countOnly) {
            $sql = "SELECT COUNT(*)";
        } else {
            $sql = "SELECT id, title, description, securityClassId, dataType";
        }

        $sql .= " FROM " . Tables::PASSWORDS;
        $where[] = 'id IN (' . implode(',', $passwordIds) . ')';

        if (isset($searchParams['searchterm']) &&
            !empty($searchParams['searchterm'])
        ) {
            $whereOR = array();

            if (isset($searchParams['title'])
                && $searchParams['title']
            ) {
                $whereOR[]      = '`title` LIKE :title';
                $binds['title'] = array(
                    'value' => '%' . $searchParams['searchterm'] . '%',
                    'type'  => \PDO::PARAM_STR
                );
            }

            if (isset($searchParams['description'])
                && $searchParams['description']
            ) {
                $whereOR[]            = '`description` LIKE :description';
                $binds['description'] = array(
                    'value' => '%' . $searchParams['searchterm'] . '%',
                    'type'  => \PDO::PARAM_STR
                );
            }

            if (!empty($whereOR)) {
                $where[] = '(' . implode(' OR ', $whereOR) . ')';
            } else {
                $where[]           = '`title` LIKE :title';
                $binds['category'] = array(
                    'value' => '%' . $searchParams['searchterm'] . '%',
                    'type'  => \PDO::PARAM_STR
                );
            }
        }

        if (isset($searchParams['passwordtypes'])
            && !empty($searchParams['passwordtypes'])
        ) {
            if (!in_array('all', $searchParams['passwordtypes'])) {
                $where[] = '`dataType` IN (\'' . implode('\',\'', $searchParams['passwordtypes']) . '\')';
            }
        }

        // build WHERE query string
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        if (isset($searchParams['sortOn']) &&
            !empty($searchParams['sortOn'])
        ) {
            $order = "ORDER BY " . Orthos::clear($searchParams['sortOn']);

            if (isset($searchParams['sortBy']) &&
                !empty($searchParams['sortBy'])
            ) {
                $order .= " " . Orthos::clear($searchParams['sortBy']);
            } else {
                $order .= " ASC";
            }

            $sql .= " " . $order;
        }

        if (isset($gridParams['limit'])
            && !empty($gridParams['limit'])
            && !$countOnly
        ) {
            $sql .= " LIMIT " . $gridParams['limit'];
        } else {
            if (!$countOnly) {
                $sql .= " LIMIT " . (int)20;
            }
        }

        $Stmt = $PDO->prepare($sql);

        // bind search values
        foreach ($binds as $var => $bind) {
            $Stmt->bindValue(':' . $var, $bind['value'], $bind['type']);
        }

        // fetch information for all corresponding passwords
        try {
            $Stmt->execute();
            $result = $Stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError('CryptoUser getPasswords() Database error :: ' . $Exception->getMessage());

            return array();
        }

        if ($countOnly) {
            return (int)current(current($result));
        }

        foreach ($result as $row) {
            $passwords[] = $row;
        }

        return $passwords;
    }
}