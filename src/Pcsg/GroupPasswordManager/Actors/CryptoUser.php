<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Actors\CryptoUser
 */

namespace Pcsg\GroupPasswordManager\Actors;

use Pcsg\GroupPasswordManager\Events;
use Pcsg\GroupPasswordManager\Handler\Categories;
use Pcsg\GroupPasswordManager\Password;
use Pcsg\GroupPasswordManager\PasswordTypes\Handler;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Authentication\Plugin;
use Pcsg\GroupPasswordManager\Security\Authentication\SecurityClass;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use Pcsg\GroupPasswordManager\Security\Keys\AuthKeyPair;
use Pcsg\GroupPasswordManager\Security\Keys\Key;
use Pcsg\GroupPasswordManager\Security\Keys\KeyPair;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\SecretSharing;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use QUI;
use Pcsg\GroupPasswordManager\Constants\Tables;
use QUI\Utils\Security\Orthos;
use Symfony\Component\Console\Helper\Table;

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
     * Runtime cache for meta data of passwords
     *
     * @var array
     */
    protected $passwordMetaData = array();

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

        if (empty($securityClassAuthPluginIds)) {
            return $keyPairs;
        }

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
     * Get CryptoGroups the user has access to (as objects)
     *
     * @return array
     */
    public function getCryptoGroups()
    {
        $groups   = array();
        $groupIds = $this->getCryptoGroupIds();

        foreach ($groupIds as $groupId) {
            $groups[] = CryptoActors::getCryptoGroup($groupId);
        }

        return $groups;
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

        return array_unique($groupIds);
    }

    /**
     * Get IDs of all groups the user has access to that have access to a specific password
     *
     * @param integer $passwordId - password ID
     * @return array - group ids
     */
    protected function getGroupIdsByPasswordId($passwordId)
    {
        $groups   = $this->getCryptoGroups();
        $groupIds = array();

        /** @var CryptoGroup $CryptoGroup */
        foreach ($groups as $CryptoGroup) {
            if (in_array($passwordId, $CryptoGroup->getPasswordIds())) {
                $groupIds[] = $CryptoGroup->getId();
            }
        }

        return $groupIds;
    }

    /**
     * Get IDs of all passwords the user has access to
     *
     * @return array
     */
    public function getPasswordIds()
    {
        return array_merge($this->getPasswordIdsDirectAccess(), $this->getPasswordIdsGroupAccess());
    }

    /**
     * Get IDs of all passwords the user has direct access to
     *
     * @return array
     */
    public function getPasswordIdsDirectAccess()
    {
        $ids = array();

        // direct access
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'dataId'
            ),
            'from'   => Tables::USER_TO_PASSWORDS,
            'where'  => array(
                'userId' => $this->getId()
            )
        ));

        foreach ($result as $row) {
            $ids[] = $row['dataId'];
        }

        return array_unique($ids);
    }

    /**
     * Get IDs of all passwords the user has acces to via a group
     *
     * @return array
     */
    public function getPasswordIdsGroupAccess()
    {
        $ids    = array();
        $groups = $this->getCryptoGroups();

        /** @var CryptoGroup $CryptoGroup */
        foreach ($groups as $CryptoGroup) {
            $ids = array_merge($ids, $CryptoGroup->getPasswordIds());
        }

        return $ids;
    }

    /**
     * Get IDs of all passwords the user owner (directly or via group)
     *
     * @return array
     */
    public function getOwnerPasswordIds()
    {
        return array_merge($this->getDirectOwnerPasswordIds(), $this->getGroupOwnerPasswordIds());
    }

    /**
     * Get IDs of all passwords the user owns directly (not via group)
     *
     * @return array - password IDs
     */
    public function getDirectOwnerPasswordIds()
    {
        $passwordIds = array();
        $result      = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => Tables::PASSWORDS,
            'where'  => array(
                'ownerId'   => $this->getId(),
                'ownerType' => Password::OWNER_TYPE_USER
            )
        ));

        foreach ($result as $row) {
            $passwordIds[] = $row['id'];
        }

        return $passwordIds;
    }

    /**
     * Get IDs of all passwords the user owns via group
     *
     * @return array - password IDs
     */
    public function getGroupOwnerPasswordIds()
    {
        $ids    = array();
        $groups = $this->getCryptoGroups();

        /** @var CryptoGroup $CryptoGroup */
        foreach ($groups as $CryptoGroup) {
            $ids = array_merge($ids, $CryptoGroup->getOwnerPasswordIds());
        }

        return $ids;
    }

    /**
     * Get password access key to decrypt a password
     *
     * @param integer $passwordId - Password ID
     * @return Key
     *
     * @throws QUI\Exception
     */
    public function getPasswordAccessKey($passwordId)
    {
        $passwordIds = $this->getPasswordIds();

        if (!in_array($passwordId, $passwordIds)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptouser.password.access.key.decrypt.user.has.no.access',
                array(
                    'userId'     => $this->getId(),
                    'passwordId' => $passwordId
                )
            ));
        }

        $PasswordKey = $this->getPasswordAccessKeyUser($passwordId);

        if (!$PasswordKey) {
            $PasswordKey = $this->getPasswordAccessKeyGroup($passwordId);
        }

        return $PasswordKey;
    }

    /**
     * Get password access key via direct access
     *
     * @param $passwordId
     * @return Key|false - Password access key or false if not found
     *
     * @throws QUI\Exception
     */
    protected function getPasswordAccessKeyUser($passwordId)
    {
        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::USER_TO_PASSWORDS,
            'where' => array(
                'userId' => $this->getId(),
                'dataId' => $passwordId
            )
        ));

        if (empty($result)) {
            return false;
        }

        $passwordKeyParts = array();

        foreach ($result as $row) {
            // check access data integrity/authenticity
            $accessDataMAC      = $row['MAC'];
            $accessDataMACCheck = MAC::create(
                implode(
                    '',
                    array(
                        $row['userId'],
                        $row['dataId'],
                        $row['dataKey'],
                        $row['keyPairId']
                    )
                ),
                Utils::getSystemKeyPairAuthKey()
            );

            if (!MAC::compare($accessDataMAC, $accessDataMACCheck)) {
                QUI\System\Log::addCritical(
                    'Password access data (uid #' . $row['userId'] . ', dataId #' . $row['dataId']
                    . ', keyPairId #' . $row['keyPairId'] . ' is possibly altered! MAC mismatch!'
                );

                // @todo eigenen 401 error code
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.password.acces.data.not.authentic',
                    array(
                        'passwordId' => $passwordId
                    )
                ));
            }

            $AuthKeyPair = Authentication::getAuthKeyPair($row['keyPairId']);
            $AuthPlugin  = $AuthKeyPair->getAuthPlugin();

            if (!$AuthPlugin->isAuthenticated($this)) {
                continue;
            }

            $passwordKeyParts[] = AsymmetricCrypto::decrypt(
                $row['dataKey'],
                $AuthKeyPair
            );
        }

        // build password key from its parts
        try {
            $PasswordKey = new Key(SecretSharing::recoverSecret($passwordKeyParts));
        } catch (\Exception $Exception) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptouser.password.key.recovery.error',
                array(
                    'userId'     => $this->getId(),
                    'passwordId' => $passwordId
                )
            ));
        }

        return $PasswordKey;
    }

    /**
     * Get password access key via group access
     *
     * @param $passwordId
     * @return Key|false - Password access key or false if not found
     *
     * @throws QUI\Exception
     */
    protected function getPasswordAccessKeyGroup($passwordId)
    {
        $accessGroupIds = $this->getGroupIdsByPasswordId($passwordId);

        if (empty($accessGroupIds)) {
            return false;
        }

        // get group key
        $groupId               = array_shift($accessGroupIds);
        $CryptoGroup           = CryptoActors::getCryptoGroup($groupId);
        $GroupKeyPairDecrypted = $this->getGroupKeyPairDecrypted(
            $CryptoGroup,
            Passwords::getSecurityClass($passwordId)
        );

        // decrypt password key with group private key
        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::GROUP_TO_PASSWORDS,
            'where' => array(
                'dataId'  => $passwordId,
                'groupId' => $groupId
            )
        ));

        $data = current($result);

        $MACData = array(
            $data['groupId'],
            $data['dataId'],
            $data['dataKey']
        );

        $MACExpected = $data['MAC'];
        $MACActual   = MAC::create(
            implode('', $MACData),
            Utils::getSystemKeyPairAuthKey()
        );

        if (!MAC::compare($MACActual, $MACExpected)) {
            QUI\System\Log::addCritical(
                'Group password key #' . $data['id'] . ' possibly altered. MAC mismatch!'
            );

            // @todo eigenen 401 error code
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.group.password.key.not.authentic',
                array(
                    'passwordId' => $this->id,
                    'groupId'    => $CryptoGroup->getId()
                )
            ));
        }

        try {
            $passwordKeyDecryptedValue = AsymmetricCrypto::decrypt(
                $data['dataKey'],
                $GroupKeyPairDecrypted
            );

            return new Key($passwordKeyDecryptedValue);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Could not build password key for password #' . $passwordId
                . ' with user #' . $this->getId()
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptouser.getpasswordaccesskeygroup.error',
                array(
                    'passwordId' => $passwordId
                )
            ));
        }
    }

    /**
     * Get key to decrypt the private key of a specific CryptoGroup (with specific security class)
     *
     * @param CryptoGroup $CryptoGroup
     * @param SecurityClass $SecurityClass
     * @return Key
     *
     * @throws QUI\Exception
     */
    public function getGroupAccessKey($CryptoGroup, $SecurityClass)
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
                'userId'          => $this->getId(),
                'groupId'         => $CryptoGroup->getId(),
                'securityClassId' => $SecurityClass->getId()
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
                $row['securityClassId'],
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

            $AuthPlugin = $AuthKeyPair->getAuthPlugin();

            if (!$AuthPlugin->isAuthenticated($this)) {
                continue;
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

        try {
            return new Key(SecretSharing::recoverSecret($accessKeyParts));
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Could not decrypt group key with user #' . $this->getId() . ' for group #' . $CryptoGroup->getId()
                . ' for securityclass #' . $SecurityClass->getId()
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptouser.getgroupaccesskey.error',
                array(
                    'userId'    => $this->getId(),
                    'userName'  => $this->getUsername(),
                    'groupId'   => $CryptoGroup->getId(),
                    'groupName' => $CryptoGroup->getAttribute('name')
                )
            ));
        }
    }

    /**
     * Get decrypted key pair of a CryptoGroup for specific security class
     *
     * @param CryptoGroup $CryptoGroup
     * @param SecurityClass $SecurityClass
     *
     * @return KeyPair
     *
     * @throws QUI\Exception
     */
    public function getGroupKeyPairDecrypted(CryptoGroup $CryptoGroup, SecurityClass $SecurityClass)
    {
        // get group access key
        $GroupAccessKey = $this->getGroupAccessKey($CryptoGroup, $SecurityClass);
        $GroupKeyPair   = $CryptoGroup->getKeyPair($SecurityClass);

        try {
            $groupPrivateKeyDecrypted = SymmetricCrypto::decrypt(
                $GroupKeyPair->getPrivateKey()->getValue(),
                $GroupAccessKey
            );

            return new KeyPair($GroupKeyPair->getPublicKey(), $groupPrivateKeyDecrypted);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Could not decrypt group key pair (group #' . $CryptoGroup->getId() . ' | security class #'
                . $SecurityClass->getId() . '): ' . $Exception->getMessage()
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptouser.getgroupkeypairdecrypted.error',
                array(
                    'groupId'            => $CryptoGroup->getId(),
                    'groupName'          => $CryptoGroup->getAttribute('name'),
                    'securityClassId'    => $SecurityClass->getId(),
                    'securityClassTitle' => $SecurityClass->getAttribute('title')
                )
            ));
        }
    }

    /**
     * Gets titles and descriptions to all passwords the user has access to
     *
     * @param array $searchParams - search options
     * @param bool $countOnly (optional) - get count only
     * @return array
     */
    public function getPasswordList($searchParams, $countOnly = false)
    {
        $PDO       = QUI::getDataBase()->getPDO();
        $passwords = array();
        $binds     = array();
        $where     = array();

        $passwordIds = $this->getPasswordIds();
        $Grid        = new \QUI\Utils\Grid($searchParams);
        $gridParams  = $Grid->parseDBParams($searchParams);

        // private category filter
        if (isset($searchParams['categoryIdPrivate']) &&
            !empty($searchParams['categoryIdPrivate'])
        ) {
            $categoryPasswordIds = $this->getPrivatePasswordIdsByCategory(
                $searchParams['categoryIdPrivate']
            );

            $passwordIds = array_intersect($passwordIds, $categoryPasswordIds);
        }

        // check if passwords found for this user - if not return empty list
        if (empty($passwordIds)) {
            return array();
        }

        if ($countOnly) {
            $sql = "SELECT COUNT(*)";
        } else {
            $selectFields = array(
                'data.`id`',
                'data.`title`',
                'data.`description`',
                'data.`securityClassId`',
                'data.`dataType`',
                'data.`ownerId`',
                'data.`ownerType`',
                'meta.`favorite`'
            );

            $sql = "SELECT " . implode(',', $selectFields);
        }

        // JOIN user access meta table with password data table
        $sql .= " FROM `" . QUI::getDBTableName(Tables::PASSWORDS) . "` data, ";
        $sql .= " `" . QUI::getDBTableName(Tables::USER_TO_PASSWORDS_META) . "` meta";

        $where[] = 'data.`id` = meta.`dataId`';
        $where[] = 'data.`id` IN (' . implode(',', $passwordIds) . ')';

        if (isset($searchParams['searchterm']) &&
            !empty($searchParams['searchterm'])
        ) {
            $whereOR = array();

            if (isset($searchParams['title'])
                && $searchParams['title']
            ) {
                $whereOR[]      = 'data.`title` LIKE :title';
                $binds['title'] = array(
                    'value' => '%' . $searchParams['searchterm'] . '%',
                    'type'  => \PDO::PARAM_STR
                );
            }

            if (isset($searchParams['description'])
                && $searchParams['description']
            ) {
                $whereOR[]            = 'data.`description` LIKE :description';
                $binds['description'] = array(
                    'value' => '%' . $searchParams['searchterm'] . '%',
                    'type'  => \PDO::PARAM_STR
                );
            }

            if (!empty($whereOR)) {
                $where[] = '(' . implode(' OR ', $whereOR) . ')';
            } else {
                $where[]        = 'data.`title` LIKE :title';
                $binds['title'] = array(
                    'value' => '%' . $searchParams['searchterm'] . '%',
                    'type'  => \PDO::PARAM_STR
                );
            }
        }

        if (isset($searchParams['passwordtypes'])
            && !empty($searchParams['passwordtypes'])
        ) {
            if (!in_array('all', $searchParams['passwordtypes'])) {
                $where[] = 'data.`dataType` IN (\'' . implode('\',\'', $searchParams['passwordtypes']) . '\')';
            }
        }

        if (isset($searchParams['categoryId'])
            && !empty($searchParams['categoryId'])
        ) {
            $where[]             = 'data.`categories` LIKE :categoryId';
            $binds['categoryId'] = array(
                'value' => '%,' . (int)$searchParams['categoryId'] . ',%',
                'type'  => \PDO::PARAM_STR
            );
        }

        // WHERE filters
        if (isset($searchParams['filters'])
            && !empty($searchParams['filters'])
        ) {
            foreach ($searchParams['filters'] as $filter) {
                switch ($filter) {
                    case 'favorites':
                        $where[] = 'meta.`favorite` = 1';
                        break;
                }
            }
        }

        // build WHERE query string
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $orderFields = array();

        // ORDER BY filters
        if (isset($searchParams['filters'])
            && !empty($searchParams['filters'])
        ) {
            foreach ($searchParams['filters'] as $filter) {
                switch ($filter) {
                    case 'new':
                        $orderFields[] = 'meta.`accessDate` DESC';
                        break;

                    case 'mostUsed':
                        $orderFields[] = 'meta.`viewCount` DESC';
                        break;
                }
            }
        }

        // Table column sort
        if (isset($searchParams['sortOn'])
            && !empty($searchParams['sortOn'])
        ) {
            $order = 'data.`' . Orthos::clear($searchParams['sortOn']) . '`';

            if (isset($searchParams['sortBy']) &&
                !empty($searchParams['sortBy'])
            ) {
                $order .= " " . Orthos::clear($searchParams['sortBy']);
            } else {
                $order .= " ASC";
            }

            $orderFields[] = $order;
        }

        if (!empty($orderFields)) {
            $sql .= " ORDER BY " . implode(',', $orderFields);
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

        $ownerPasswordIds        = $this->getOwnerPasswordIds();
        $directAccessPasswordIds = $this->getPasswordIdsDirectAccess();

        foreach ($result as $row) {
            $row['isOwner'] = in_array($row['id'], $ownerPasswordIds);

            if (in_array($row['id'], $directAccessPasswordIds)) {
                $row['access'] = 'user';
            } else {
                $row['access'] = 'group';
            }

            $row['dataType'] = Handler::getTypeTitle($row['dataType']);

            switch ($row['ownerType']) {
                case '1':
                    $row['ownerName'] = QUI::getUsers()->get($row['ownerId'])->getUsername();
                    break;

                case '2':
                    $row['ownerName'] = QUI::getGroups()->get($row['ownerId'])->getName();
                    break;
            }

            $passwords[] = $row;
        }

        // check if passwords are shared
        $passwordIdsSharedWithUsers  = $this->getOwnerPasswordIdsSharedWithUsers();
        $passwordIdsSharedWithGroups = $this->getOwnerPasswordIdsSharedWithGroups();

        // set results to password list
        foreach ($passwords as $k => $row) {
            $row['sharedWithUsers']  = false;
            $row['sharedWithGroups'] = false;

            if (in_array($row['id'], $passwordIdsSharedWithUsers)) {
                $row['sharedWithUsers'] = true;
            }

            if (in_array($row['id'], $passwordIdsSharedWithGroups)) {
                $row['sharedWithGroups'] = true;
            }

            $passwords[$k] = $row;
        }

        return $passwords;
    }

    /**
     * Get IDs of all passwords belonging to a private password category
     *
     * @param $categoryId
     * @param QUI\Users\User $User (optional) - category owner (if omitted = session user)
     * @return array
     */
    public function getPrivatePasswordIdsByCategory($categoryId, $User = null)
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'dataId'
            ),
            'from'   => QUI::getDBTableName(Tables::USER_TO_PASSWORDS_META),
            'where'  => array(
                'userId'     => $this->id,
                'categories' => array(
                    'type'  => '%LIKE%',
                    'value' => ',' . (int)$categoryId . ','
                )
            )
        ));

        $passwordIds = array();

        foreach ($result as $row) {
            $passwordIds[] = $row['dataId'];
        }

        return $passwordIds;
    }

    /**
     * Get IDs of all passwords this user owns and that are shared with other users
     *
     * @return array
     */
    public function getOwnerPasswordIdsSharedWithUsers()
    {
        $passwordIds = array();

        $where = array(
            'userId' => array(
                'type'  => 'NOT',
                'value' => $this->id
            )
        );

        $passwordIdsDirectAccess = $this->getPasswordIdsDirectAccess();

        if (!empty($passwordIdsDirectAccess)) {
            $where['dataId'] = array(
                'type'  => 'IN',
                'value' => $passwordIdsDirectAccess
            );
        }

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'dataId'
            ),
            'from'   => Tables::USER_TO_PASSWORDS,
            'where'  => $where
        ));

        foreach ($result as $row) {
            $passwordIds[] = $row['dataId'];
        }

        return array_unique($passwordIds);
    }

    /**
     * Get IDs of all passwords this user owns and that are shared with other users and groups
     *
     * @return array
     */
    public function getOwnerPasswordIdsShared()
    {
        return array_merge(
            $this->getOwnerPasswordIdsSharedWithUsers(),
            $this->getOwnerPasswordIdsSharedWithGroups()
        );
    }

    /**
     * Get IDs of all passwords this user owns and that are shared with other groups
     *
     * @return array
     */
    public function getOwnerPasswordIdsSharedWithGroups()
    {
        $passwordIds           = array();
        $where                 = array();
        $groupOwnerPasswordIds = $this->getGroupOwnerPasswordIds();

        if (!empty($groupOwnerPasswordIds)) {
            $where['dataId'] = array(
                'type'  => 'NOT IN',
                'value' => $groupOwnerPasswordIds
            );
        }

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'dataId'
            ),
            'from'   => Tables::GROUP_TO_PASSWORDS,
            'where'  => $where
        ));

        foreach ($result as $row) {
            $passwordIds[] = $row['dataId'];
        }

        return $passwordIds;
    }

    /**
     * Get IDs of authentication plugins this user is not registered for
     *
     * @return array
     */
    public function getNonRegisteredAuthPluginIds()
    {
        $authPlugins                = Authentication::getAuthPlugins();
        $nonRegisteredAuthPluginIds = array();

        /** @var Plugin $AuthPlugin */
        foreach ($authPlugins as $AuthPlugin) {
            if (!$AuthPlugin->isRegistered($this)) {
                $nonRegisteredAuthPluginIds[] = $AuthPlugin->getId();
            }
        }

        return $nonRegisteredAuthPluginIds;
    }

    /**
     * Get IDs of all passwords the user has access to only via OTHER authentication plugins
     * than the one provided as the method argument.
     *
     * Background:
     * If a password is assigned to a security class that requires an authentication plugin
     * the user previously has not been registered with, the user can re-encrypt all those
     * passwords IF he decides to register with said authentication plugin. In case of a
     * registration this method can be used to get all password IDs that need such a re-encryption.
     *
     * @param Plugin $AuthPlugin
     * @return array - password IDs
     */
    public function getNonFullyAccessiblePasswordIds(Plugin $AuthPlugin)
    {
        if (!$AuthPlugin->isRegistered($this)) {
            return array();
        }

        $cname = 'pcsg/gpm/cryptouser/nonfullyaccessiblepasswordids/' . $AuthPlugin->getId();

        try {
            return QUI\Cache\Manager::get($cname);
        } catch (\Exception $Exception) {
            // nothing, determine ids
        }

        $passwordIds = array();
        $AuthKeyPair = $this->getAuthKeyPair($AuthPlugin);

        // direct access
        $authPluginAccessDirect = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'dataId'
            ),
            'from'   => Tables::USER_TO_PASSWORDS,
            'where'  => array(
                'userId'    => $this->getId(),
                'keyPairId' => $AuthKeyPair->getId()
            )
        ));

        foreach ($result as $row) {
            $authPluginAccessDirect[$row['dataId']] = true;
        }

        // group access
//        $authPluginAccessGroup = array();

//        $result = QUI::getDataBase()->fetch(array(
//            'select' => array(
//                'groupId'
//            ),
//            'from'   => Tables::USER_TO_GROUPS,
//            'where'  => array(
//                'userId'        => $this->getId(),
//                'userKeyPairId' => $AuthKeyPair->getId()
//            )
//        ));
//
//        foreach ($result as $row) {
//            $CryptoGroup      = CryptoActors::getCryptoGroup($row['groupId']);
//            $groupPasswordIds = $CryptoGroup->getPasswordIds();
//
//            foreach ($groupPasswordIds as $groupPasswordId) {
//                $authPluginAccessGroup[$groupPasswordId] = true;
//            }
//        }

        // check which password ids apply
        $accessPasswordIds       = $this->getPasswordIds();
        $accessPasswordIdsDirect = $this->getPasswordIdsDirectAccess();
        $authPluginId            = $AuthPlugin->getId();

        foreach ($accessPasswordIds as $passwordId) {
            $SecurityClass              = Passwords::getSecurityClass($passwordId);
            $securityClassAuthPluginIds = $SecurityClass->getAuthPluginIds();

            if (!in_array($authPluginId, $securityClassAuthPluginIds)) {
                continue;
            }

            if (in_array($passwordId, $accessPasswordIdsDirect)) {
                if (!isset($authPluginAccessDirect[$passwordId])) {
                    $passwordIds[] = $passwordId;
                }
            }
        }

        $passwordIds = array_unique($passwordIds);

        QUI\Cache\Manager::set($cname, $passwordIds);

        return $passwordIds;
    }

    /**
     * Get IDs of groups and security classes that are NOT encrypted with a specific
     * authentication plugin
     *
     * @param Plugin $AuthPlugin
     * @return array
     */
    public function getNonFullyAccessibleGroupAndSecurityClassIds(Plugin $AuthPlugin)
    {
        if (!$AuthPlugin->isRegistered($this)) {
            return array();
        }

        $cname = 'pcsg/gpm/cryptouser/nonfullyaccessiblegroupandsecurityclassids/' . $AuthPlugin->getId();

        try {
            return QUI\Cache\Manager::get($cname);
        } catch (\Exception $Exception) {
            // nothing, determine ids
        }

        $AuthKeyPair = $this->getAuthKeyPair($AuthPlugin);

        // group access
        $groupAccess   = array();
        $limitedAccess = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'groupId',
                'securityClassId'
            ),
            'from'   => Tables::USER_TO_GROUPS,
            'where'  => array(
                'userId'        => $this->getId(),
                'userKeyPairId' => $AuthKeyPair->getId()
            )
        ));

        foreach ($result as $row) {
            $groupId = $row['groupId'];

            if (!isset($groupAccess[$groupId])) {
                $groupAccess[$groupId] = array();
            }

            $groupAccess[$groupId][] = $row['securityClassId'];
        }

        $allAccessGroupIds = $this->getCryptoGroupIds();

        foreach ($allAccessGroupIds as $groupId) {
            $CryptoGroup          = CryptoActors::getCryptoGroup($groupId);
            $groupSecurityClasses = $CryptoGroup->getSecurityClasses();

            /** @var SecurityClass $SecurityClass */
            foreach ($groupSecurityClasses as $SecurityClass) {
                if (!in_array($AuthPlugin->getId(), $SecurityClass->getAuthPluginIds())) {
                    continue;
                }

                if (!isset($groupAccess[$groupId])
                    || !in_array($SecurityClass->getId(), $groupAccess[$groupId])
                ) {
                    if (!isset($limitedAccess[$groupId])) {
                        $limitedAccess[$groupId] = array();
                    }

                    $limitedAccess[$groupId][] = $SecurityClass->getId();
                }
            }
        }

        QUI\Cache\Manager::set($cname, $limitedAccess);

        return $limitedAccess;
    }

    /**
     * Takes a password access key and re-encrypts it with the current
     * number of authentication key pairs the user has registered with, according to the respective
     * security class of a password.
     *
     * @param integer $passwordId - password ID
     * @return void
     * @throws QUI\Exception
     */
    public function reEncryptPasswordAccessKey($passwordId)
    {
        $passwordIdsDirectAccess = $this->getPasswordIdsDirectAccess();

        if (!in_array($passwordId, $passwordIdsDirectAccess)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptouser.rencryptpasswordaccessKey.no.direct.access',
                array(
                    'userId'     => $this->getId(),
                    'passwordId' => $passwordId
                )
            ));
        }

        $Password      = Passwords::get($passwordId);
        $PasswordKey   = $Password->getPasswordKey();
        $SecurityClass = $Password->getSecurityClass();

        if (!$SecurityClass->isUserEligible($this)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptouser.rencryptpasswordaccessKey.securityclass.not.eligible',
                array(
                    'userId'             => $this->getId(),
                    'userName'           => $this->getUsername(),
                    'passwordId'         => $passwordId,
                    'securityClassId'    => $SecurityClass->getId(),
                    'securityClassTitle' => $SecurityClass->getAttribute('title')
                )
            ));
        }

        // split key
        $passwordKeyParts = SecretSharing::splitSecret(
            $PasswordKey->getValue(),
            $SecurityClass->getAuthPluginCount(),
            $SecurityClass->getRequiredFactors()
        );

        $authKeyPairs = $this->getAuthKeyPairsBySecurityClass($SecurityClass);
        $i            = 0;
        $DB           = QUI::getDataBase();

        /** @var AuthKeyPair $UserAuthKeyPair */
        foreach ($authKeyPairs as $UserAuthKeyPair) {
            try {
                // delete old access entry
                $DB->delete(
                    Tables::USER_TO_PASSWORDS,
                    array(
                        'userId'    => $this->getId(),
                        'keyPairId' => $UserAuthKeyPair->getId(),
                        'dataId'    => $passwordId
                    )
                );

                $encryptedPasswordKeyPart = AsymmetricCrypto::encrypt(
                    $passwordKeyParts[$i++],
                    $UserAuthKeyPair
                );

                $dataAccessEntry = array(
                    'userId'    => $this->getId(),
                    'dataId'    => $passwordId,
                    'dataKey'   => $encryptedPasswordKeyPart,
                    'keyPairId' => $UserAuthKeyPair->getId()
                );

                $dataAccessEntry['MAC'] = MAC::create(
                    implode('', $dataAccessEntry),
                    Utils::getSystemKeyPairAuthKey()
                );

                $DB->insert(
                    Tables::USER_TO_PASSWORDS,
                    $dataAccessEntry
                );
            } catch (\Exception $Exception) {
                QUI\System\Log::addError(
                    'CryptoUser :: reEncryptPasswordAccessKey() :: Error writing password key parts to database: '
                    . $Exception->getMessage()
                );

                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.crptouser.rencryptpasswordaccessKey.general.error'
                ));
            }
        }
    }

    /**
     * Takes a password access key and re-encrypts it with the current
     * number of authentication key pairs the user has registered with, according to the respective
     * security class of a password.
     *
     * @param integer $passwordId - password ID
     * @return void
     * @throws QUI\Exception
     */
    public function reEncryptGroupAccessKey(CryptoGroup $CryptoGroup, SecurityClass $SecurityClass)
    {
        if (!$CryptoGroup->hasCryptoUserAccess($this)) {
            // @todo fehlermeldung
            return;
        }

        if (!$CryptoGroup->hasSecurityClass($SecurityClass)) {
            // @todo fehlermeldung
            return;
        }

        if (!$SecurityClass->isUserEligible($this)) {
            // @todo fehlermeldung
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptouser.rencryptpasswordaccessKey.securityclass.not.eligible',
                array(
                    'userId'             => $this->getId(),
                    'userName'           => $this->getUsername(),
                    'securityClassId'    => $SecurityClass->getId(),
                    'securityClassTitle' => $SecurityClass->getAttribute('title')
                )
            ));
        }

        // split key
        $GroupAccessKey = $this->getGroupAccessKey($CryptoGroup, $SecurityClass);

        $groupAccessKeyParts = SecretSharing::splitSecret(
            $GroupAccessKey->getValue(),
            $SecurityClass->getAuthPluginCount(),
            $SecurityClass->getRequiredFactors()
        );

        // encrypt key parts with user public keys
        $i            = 0;
        $authKeyPairs = $this->getAuthKeyPairsBySecurityClass($SecurityClass);
        $DB           = QUI::getDataBase();

        /** @var AuthKeyPair $UserAuthKeyPair */
        foreach ($authKeyPairs as $UserAuthKeyPair) {
            try {
                // delete old access entry
                $DB->delete(
                    Tables::USER_TO_GROUPS,
                    array(
                        'userId'          => $this->getId(),
                        'userKeyPairId'   => $UserAuthKeyPair->getId(),
                        'groupId'         => $CryptoGroup->getId(),
                        'securityClassId' => $SecurityClass->getId()
                    )
                );

                $payloadKeyPart = $groupAccessKeyParts[$i++];

                $groupAccessKeyPartEncrypted = AsymmetricCrypto::encrypt(
                    $payloadKeyPart,
                    $UserAuthKeyPair
                );

                $data = array(
                    'userId'          => $this->getId(),
                    'userKeyPairId'   => $UserAuthKeyPair->getId(),
                    'securityClassId' => $SecurityClass->getId(),
                    'groupId'         => $CryptoGroup->getId(),
                    'groupKey'        => $groupAccessKeyPartEncrypted
                );

                // calculate MAC
                $data['MAC'] = MAC::create(implode('', $data), Utils::getSystemKeyPairAuthKey());

                $DB->insert(Tables::USER_TO_GROUPS, $data);
            } catch (\Exception $Exception) {
                QUI\System\Log::addError(
                    'Error writing group key parts to database: ' . $Exception->getMessage()
                );

                QUI::getDataBase()->delete(
                    Tables::USER_TO_GROUPS,
                    array(
                        'userId'          => $this->getId(),
                        'groupId'         => $CryptoGroup->getId(),
                        'securityClassId' => $SecurityClass->getId()
                    )
                );

                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptogroup.add.user.general.error',
                    array(
                        'userId'  => $CryptoGroup->getId(),
                        'groupId' => $this->getId()
                    )
                ));
            }
        }
    }

    /**
     * Takes all password and group access keys and re-encrypts them with the current
     * number of authentication key pairs the user has registered according to the respective
     * security class of a password.
     *
     * ATTENTION: This process may take several seconds to complete!
     *
     * @return void
     * @throws QUI\Exception
     */
    public function reEncryptAllPasswordAccessKeys()
    {
        // re encrypt direct access
        $passwordIdsDirect = $this->getPasswordIdsDirectAccess();

        foreach ($passwordIdsDirect as $passwordId) {
            $this->reEncryptPasswordAccessKey($passwordId);
        }
    }

    /**
     * Increase personal view count for a password
     *
     * @param int $passwordId - Password ID
     * @return void
     */
    public function increasePasswordViewCount($passwordId)
    {
        $passwordId = (int)$passwordId;
        $metaData   = $this->getPasswordMetaData($passwordId);

        if (!isset($metaData['viewCount'])) {
            return;
        }

        $currentViewCount = $metaData['viewCount'];

        QUI::getDataBase()->update(
            QUI::getDBTableName(Tables::USER_TO_PASSWORDS_META),
            array(
                'viewCount' => ++$currentViewCount
            ),
            array(
                'userId' => $this->id,
                'dataId' => $passwordId
            )
        );

        $this->passwordMetaData[$passwordId]['viewCount'] = $currentViewCount;
    }

    /**
     * Get password metadata
     *
     * @param $passwordId
     * @return array
     */
    public function getPasswordMetaData($passwordId)
    {
        if (isset($this->passwordMetaData[$passwordId])) {
            return $this->passwordMetaData[$passwordId];
        }

        $result = QUI::getDataBase()->fetch(array(
            'from'  => QUI::getDBTableName(Tables::USER_TO_PASSWORDS_META),
            'where' => array(
                'userId' => $this->id,
                'dataId' => $passwordId
            )
        ));

        if (empty($result)) {
            return array();
        }

        $data = current($result);
        unset($data['userId']);

        $this->passwordMetaData[$passwordId] = $data;

        return $data;
    }

    /**
     * Set favorite status to password
     *
     * @param int $passwordId - Password ID
     * @param bool $status - true = favorite; false = unfavorite
     * @return void
     */
    public function setPasswordFavoriteStatus($passwordId, $status = true)
    {
        QUI::getDataBase()->update(
            QUI::getDBTableName(Tables::USER_TO_PASSWORDS_META),
            array(
                'favorite' => $status ? 1 : 0
            ),
            array(
                'userId' => $this->id,
                'dataId' => (int)$passwordId
            )
        );
    }

    /**
     * Delete crypto user (and QUIQQER user) permanently
     *
     * @return void
     * @throws QUI\Exception
     */
    public function delete()
    {
        // users can only be deleted by themselves or super users
        $SessionUser = QUI::getUserBySession();

        if ((int)$SessionUser->getId() !== (int)$this->getId()
            && !$SessionUser->isSU()
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptouser.delete.no.permission'
            ));
        }

        // check if user is last user of any CryptoGroups
        $groups = $this->getCryptoGroups();

        /** @var CryptoGroup $CryptoGroup */
        foreach ($groups as $CryptoGroup) {
            $userCount = (int)$CryptoGroup->countUser();

            if ($userCount <= 1) {
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptouser.delete.last.group.member',
                    array(
                        'groupId'   => $CryptoGroup->getId(),
                        'groupName' => $CryptoGroup->getAttribute('name')
                    )
                ));
            }
        }

        // remove user from all crypto groups
        /** @var CryptoGroup $CryptoGroup */
        foreach ($groups as $CryptoGroup) {
            $CryptoGroup->removeCryptoUser($this);
        }

        // delete all passwords the user owns directly (not via group)
        $ownerPasswordIds = $this->getDirectOwnerPasswordIds();

        foreach ($ownerPasswordIds as $passwordId) {
            $Password = Passwords::get($passwordId);
            $Password->delete();
        }

        $DB = QUI::getDataBase();

        // delete all password access data
        $DB->delete(
            Tables::USER_TO_PASSWORDS,
            array(
                'userId' => $this->getId()
            )
        );

        // delete auth plugin users
        $authPlugins = Authentication::getAuthPlugins();

        /** @var Plugin $AuthPlugin */
        foreach ($authPlugins as $AuthPlugin) {
            $AuthPlugin->deleteUser($this);
        }

        // delete keypairs
        $DB->delete(
            Tables::KEYPAIRS_USER,
            array(
                'userId' => $this->getId()
            )
        );

        // delete recovery data
        $DB->delete(
            Tables::RECOVERY,
            array(
                'userId' => $this->getId()
            )
        );

        Events::$triggerUserDeleteConfirm = false;

        parent::delete();
    }
}
