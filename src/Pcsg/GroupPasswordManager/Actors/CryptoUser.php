<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Actors\CryptoUser
 */

namespace Pcsg\GroupPasswordManager\Actors;

use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Authentication\Plugin;
use Pcsg\GroupPasswordManager\Security\Authentication\SecurityClass;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Keys\AuthKeyPair;
use Pcsg\GroupPasswordManager\Security\Keys\Key;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\SecretSharing;
use Pcsg\GroupPasswordManager\Security\Utils;
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
            'from'   => Tables::KEYPAIRS_USER,
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

        return Authentication::getAuthKeyPair($data['id']);
    }

    /**
     * Get all authentication key pairs of a security class the user is registered for
     *
     * @param SecurityClass $SecurityClass
     * @return array
     */
    public function getAuthKeyPairsBySecurityClass($SecurityClass)
    {
        $keyPairs                   = array();
        $securityClassAuthPluginIds = $SecurityClass->getAuthPluginIds();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => Tables::KEYPAIRS_USER,
            'where'  => array(
                'userId'       => $this->id,
                'authPluginId' => array(
                    'type'  => 'IN',
                    'value' => $securityClassAuthPluginIds
                )
            )
        ));

        foreach ($result as $row) {
            $keyPairs[] = Authentication::getAuthKeyPair($row['id']);
        }

        return $keyPairs;
    }


    /**
     * Get IDs of all authentication key pairs the user is registered for
     *
     * @return array
     */
    protected function getAuthKeyPairIds()
    {
        $ids = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => Tables::KEYPAIRS_USER,
            'where'  => array(
                'userId' => $this->id
            )
        ));

        foreach ($result as $row) {
            $ids[] = $row['id'];
        }

        return $ids;
    }

    /**
     * Get IDs of CryptoGroups the user has access to
     *
     * @return array
     */
    public function getCryptoGroupIds()
    {
        $groupIds = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'groupId'
            ),
            'from'   => Tables::USER_TO_GROUPS,
            'where'  => array(
                'userId' => $this->id
            )
        ));

        foreach ($result as $row) {
            $groupIds[] = $row['groupId'];
        }

        return $groupIds;
    }

    /**
     * Get key to decrypt the private key of a specific CryptoGroup
     *
     * @param CryptoGroup $CryptoGroup
     * @return Key
     *
     * @throws QUI\Exception
     */
    public function getGroupAccessKey($CryptoGroup)
    {
        if (!$CryptoGroup->hasCryptoUserAccess($this)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptouser.group.access.key.decrypt.user.has.no.access',
                array(
                    'userId'  => $this->getId(),
                    'groupId' => $CryptoGroup->getId()
                )
            ));
        }

        // get parts of group access key
        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::USER_TO_GROUPS,
            'where' => array(
                'userId'  => $this->getId(),
                'groupId' => $CryptoGroup->getId()
            )
        ));

        // assemble group access key
        $accessKeyParts = array();

        foreach ($result as $row) {
            try {
                $AuthKeyPair = Authentication::getAuthKeyPair($row['userKeyPairId']);
            } catch (\Exception $Exception) {
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptouser.group.access.key.part.auth.key.error',
                    array(
                        'groupId'       => $CryptoGroup->getId(),
                        'authKeyPairId' => $row['userKeyPairId'],
                        'error'         => $Exception->getMessage()
                    )
                ));
            }

            // check integrity / authenticity of key part
            $MACData = array(
                $row['userId'],
                $row['userKeyPairId'],
                $row['groupId'],
                $row['groupKey']
            );

            $MACExcpected = $row['MAC'];
            $MACActual    = MAC::create(
                implode('', $MACData),
                Utils::getSystemKeyPairAuthKey()
            );

            if (!MAC::compare($MACActual, $MACExcpected)) {
                QUI\System\Log::addCritical(
                    'Group key part #' . $row['id'] . ' possibly altered. MAC mismatch!'
                );

                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptouser.group.access.key.part.not.authentic',
                    array(
                        'userId'  => $this->getId(),
                        'groupId' => $CryptoGroup->getId()
                    )
                ));
            }

            try {
                $accessKeyParts[] = AsymmetricCrypto::decrypt(
                    $row['groupKey'],
                    $AuthKeyPair
                );
            } catch (\Exception $Exception) {
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptouser.keypair.decryption.authentication.error',
                    array(
                        'userId'        => $this->getId(),
                        'authKeyPairId' => $AuthKeyPair->getId(),
                        'groupId'       => $CryptoGroup->getId(),
                        'error'         => $Exception->getMessage()
                    )
                ));
            }
        }

        return new Key(SecretSharing::recoverSecret($accessKeyParts));
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

        // fetch all password ids the user has (direct)access to
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'dataId'
            ),
            'from'   => Tables::USER_TO_PASSWORDS,
            'where'  => array(
                'userId' => $this->getId()
            )
        ));

        $passwordIdsAssoc = array();

        foreach ($result as $row) {
            $passwordIdsAssoc[$row['dataId']] = true;
        }

        // fetch all password ids the user has access to via groups
        $groupIds = $this->getCryptoGroupIds();

        if (!empty($groupIds)) {
            $result = QUI::getDataBase()->fetch(array(
                'select' => array(
                    'dataId'
                ),
                'from'   => Tables::GROUP_TO_PASSWORDS,
                'where'  => array(
                    'groupId' => array(
                        'type'  => 'IN',
                        'value' => $groupIds
                    )
                )
            ));
        }

        foreach ($result as $row) {
            $passwordIdsAssoc[$row['dataId']] = true;
        }

        $passwordIds = array_keys($passwordIdsAssoc);
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