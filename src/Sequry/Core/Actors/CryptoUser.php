<?php

/**
 * This file contains \Sequry\Core\Actors\CryptoUser
 */

namespace Sequry\Core\Actors;

use Sequry\Core\Constants\Permissions;
use Sequry\Core\Events;
use Sequry\Core\Exception\Exception;
use Sequry\Core\Password;
use Sequry\Core\PasswordTypes\Handler as PasswordTypesHandler;
use Sequry\Core\Security\AsymmetricCrypto;
use Sequry\Core\Security\Authentication\Plugin;
use Sequry\Core\Security\Authentication\SecurityClass;
use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Security\Handler\PasswordLinks;
use Sequry\Core\Security\Handler\Passwords;
use Sequry\Core\Security\HiddenString;
use Sequry\Core\Security\Keys\AuthKeyPair;
use Sequry\Core\Security\Keys\Key;
use Sequry\Core\Security\Keys\KeyPair;
use Sequry\Core\Security\MAC;
use Sequry\Core\Security\SecretSharing;
use Sequry\Core\Security\SymmetricCrypto;
use Sequry\Core\Security\Utils;
use QUI;
use Sequry\Core\Constants\Tables;
use QUI\Utils\Security\Orthos;
use QUI\Permissions\Permission;
use QUI\Cache\Manager as CacheManager;
use QUI\Utils\Grid;

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
    public function getAuthKeyPair(Plugin $AuthPlugin)
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => Tables::keyPairsUser(),
            'where'  => array(
                'authPluginId' => $AuthPlugin->getId(),
                'userId'       => $this->getId()
            ),
            'limit'  => 1
        ));

        if (empty($result)) {
            throw new QUI\Exception(array(
                'sequry/core',
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
     * Checks if the User has a KeyPair for an Authentication Plugin
     *
     * @param Plugin $AuthPlugin
     * @return bool
     */
    public function hasAuthKeyPair(Plugin $AuthPlugin)
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => Tables::keyPairsUser(),
            'where'  => array(
                'authPluginId' => $AuthPlugin->getId(),
                'userId'       => $this->getId()
            ),
            'limit'  => 1
        ));

        return !empty($result);
    }

    /**
     * Get all authentication key pairs of a security class the user is registered for
     *
     * @param SecurityClass $SecurityClass
     * @return AuthKeyPair[]
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
            'from'   => Tables::keyPairsUser(),
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
    public function getAuthKeyPairIds()
    {
        $ids = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => Tables::keyPairsUser(),
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
            'from'   => Tables::usersToGroups(),
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
            'from'   => Tables::usersToPasswords(),
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
            'from'   => Tables::passwords(),
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
                'sequry/core',
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
     * Get information about if and how this User can access a Password
     *
     * @param Password $Password
     * @return array
     * @throws QUI\Exception
     */
    public function getPasswordAccessInfo(Password $Password)
    {
        $SecurityClass = $Password->getSecurityClass();
        $canAccess     = $Password->hasPasswordAccess($this);

        $accessInfo = array(
            'canAccess'          => $canAccess,
            'missingAuthPlugins' => array(),
            'securityClass'      => array(
                'id'    => $SecurityClass->getId(),
                'title' => $SecurityClass->getAttribute('title')
            )
        );

        if ($canAccess) {
            return $accessInfo;
        }

        // determine missing registrations for authentication plugins
        foreach ($SecurityClass->getAuthPlugins() as $AuthPlugin) {
            if (!$this->hasAuthKeyPair($AuthPlugin)) {
                $accessInfo['missingAuthPlugins'][] = array(
                    'id'    => $AuthPlugin->getId(),
                    'title' => $AuthPlugin->getAttribute('title')
                );
            }
        }

        return $accessInfo;
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
            'from'  => Tables::usersToPasswords(),
            'where' => array(
                'userId' => $this->getId(),
                'dataId' => $passwordId
            )
        ));

        if (empty($result)) {
            return false;
        }

        $passwordKeyParts = array();
        $SecurityClass    = Passwords::getSecurityClass($passwordId);

        foreach ($result as $row) {
            // check access data integrity/authenticity
            $accessDataMAC      = $row['MAC'];
            $accessDataMACCheck = MAC::create(
                new HiddenString(implode(
                    '',
                    array(
                        $row['userId'],
                        $row['dataId'],
                        $row['dataKey'],
                        $row['keyPairId']
                    )
                )),
                Utils::getSystemKeyPairAuthKey()
            );

            if (!MAC::compare($accessDataMAC, $accessDataMACCheck)) {
                QUI\System\Log::addCritical(
                    'Password access data (uid #' . $row['userId'] . ', dataId #' . $row['dataId']
                    . ', keyPairId #' . $row['keyPairId'] . ' is possibly altered! MAC mismatch!'
                );

                // @todo eigenen 401 error code
                throw new QUI\Exception(array(
                    'sequry/core',
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

            if (count($passwordKeyParts) >= $SecurityClass->getRequiredFactors()) {
                break;
            }
        }

        // build password key from its parts
        try {
            $PasswordKey = new Key(SecretSharing::recoverSecret($passwordKeyParts));
        } catch (\Exception $Exception) {
            throw new QUI\Exception(array(
                'sequry/core',
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
            'from'  => Tables::groupsToPasswords(),
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
            new HiddenString(implode('', $MACData)),
            Utils::getSystemKeyPairAuthKey()
        );

        if (!MAC::compare($MACActual, $MACExpected)) {
            QUI\System\Log::addCritical(
                'Group password key #' . $data['id'] . ' possibly altered. MAC mismatch!'
            );

            // @todo eigenen 401 error code
            throw new QUI\Exception(array(
                'sequry/core',
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
                'sequry/core',
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
        if (!$CryptoGroup->isUserInGroup($this)) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptouser.group.access.key.decrypt.user.has.no.access',
                array(
                    'userId'  => $this->getId(),
                    'groupId' => $CryptoGroup->getId()
                )
            ));
        }

        // get parts of group access key
        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::usersToGroups(),
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
                    'sequry/core',
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
                new HiddenString(implode('', $MACData)),
                Utils::getSystemKeyPairAuthKey()
            );

            if (!MAC::compare($MACActual, $MACExcpected)) {
                QUI\System\Log::addCritical(
                    'Group key part #' . $row['id'] . ' possibly altered. MAC mismatch!'
                );

                throw new QUI\Exception(array(
                    'sequry/core',
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
                    'sequry/core',
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
                'sequry/core',
                'exception.cryptouser.getgroupaccesskey.error',
                array(
                    'userId'    => $this->getId(),
                    'userName'  => $this->getName(),
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
                $GroupKeyPair->getPrivateKey()->getValue()->getString(),
                $GroupAccessKey
            );

            return new KeyPair($GroupKeyPair->getPublicKey()->getValue(), $groupPrivateKeyDecrypted);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Could not decrypt group key pair (group #' . $CryptoGroup->getId() . ' | security class #'
                . $SecurityClass->getId() . '): ' . $Exception->getMessage()
            );

            throw new QUI\Exception(array(
                'sequry/core',
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
     * @return array|int - passwords or password count (depending on $countOnly)
     */
    public function getPasswordList($searchParams, $countOnly = false)
    {
        $PDO       = QUI::getDataBase()->getPDO();
        $passwords = array();
        $binds     = array();
        $where     = array();

        $passwordIds = $this->getPasswordIds();
        $Grid        = new QUI\Utils\Grid($searchParams);
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
        $sql .= " FROM `" . Tables::passwords() . "` data, ";
        $sql .= " `" . Tables::usersToPasswordMeta() . "` meta";

        $where[] = 'data.`id` = meta.`dataId`';
        $where[] = 'meta.`userId` = ' . $this->id;
        $where[] = 'data.`id` IN (' . implode(',', $passwordIds) . ')';

        if (!empty($searchParams['search']['searchterm'])) {
            $whereOR    = array();
            $searchTerm = trim($searchParams['search']['searchterm']);

            $searchTitle       = !empty($searchParams['title']);
            $searchDescription = !empty($searchParams['description']);

            if (!$searchTitle && !$searchDescription) {
                $searchTitle       = true;
                $searchDescription = true;
            }

            if ($searchTitle) {
                $whereOR[]      = 'data.`title` LIKE :title';
                $binds['title'] = array(
                    'value' => '%' . $searchTerm . '%',
                    'type'  => \PDO::PARAM_STR
                );
            }

            if ($searchDescription) {
                $whereOR[]            = 'data.`description` LIKE :description';
                $binds['description'] = array(
                    'value' => '%' . $searchTerm . '%',
                    'type'  => \PDO::PARAM_STR
                );
            }

            if (!empty($whereOR)) {
                $where[] = '(' . implode(' OR ', $whereOR) . ')';
            }
        }

        if (!empty($searchParams['search']['passwordtypes'])) {
            $pwTypes = $searchParams['search']['passwordtypes'];

            if (!in_array('all', $pwTypes)) {
                foreach ($pwTypes as $k => $v) {
                    if (!PasswordTypesHandler::existsType($v)) {
                        unset($pwTypes[$k]);
                    }
                }

                if (!empty($pwTypes)) {
                    $where[] = 'data.`dataType` IN (\'' . implode('\',\'', $pwTypes) . '\')';
                }
            }
        }

        if (!empty($searchParams['categoryId'])) {
            $where[]             = 'data.`categories` LIKE :categoryId';
            $binds['categoryId'] = array(
                'value' => '%,' . (int)$searchParams['categoryId'] . ',%',
                'type'  => \PDO::PARAM_STR
            );
        }

        if (!empty($searchParams['search']['uncategorized'])) {
            $where[] = 'data.`categoryIds` IS NULL';
        }

        if (!empty($searchParams['search']['uncategorizedPrivate'])) {
            $where[] = 'meta.`categoryIds` IS NULL';
        }

        // WHERE filters
        if (!empty($searchParams['filters'])) {
            if (!empty($searchParams['filters']['filters'])) {
                foreach ($searchParams['filters']['filters'] as $filter) {
                    switch ($filter) {
                        case 'favorites':
                            $where[] = 'meta.`favorite` = 1';
                            break;

                        case 'owned':
                            $where[] = 'data.`ownerId` = ' . $this->id;
                            break;
                    }
                }
            }

            if (!empty($searchParams['filters']['types'])) {
                $whereOr = array();

                foreach ($searchParams['filters']['types'] as $type) {
                    if (!is_string($type)) {
                        continue;
                    }

                    $whereOr[]    = 'data.`dataType` = :' . $type;
                    $binds[$type] = array(
                        'value' => $type,
                        'type'  => \PDO::PARAM_STR
                    );
                }

                if (!empty($whereOr)) {
                    $where[] = '(' . implode(' OR ', $whereOr) . ')';
                }
            }
        }

        // build WHERE query string
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $orderFields = array();

        // ORDER BY filters
        if (!empty($searchParams['filters']['filters'])
        ) {
            foreach ($searchParams['filters']['filters'] as $filter) {
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
        if (!empty($searchParams['sortOn'])) {
            $orderPrefix = 'data.`';

            switch ($searchParams['sortOn']) {
                case 'favorite':
                    $orderPrefix = 'meta.`';
                    break;

                default:
            }

            $order = $orderPrefix . Orthos::clear($searchParams['sortOn']) . '`';

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

        if (!empty($gridParams['limit'])
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

        $canShareOwn = Permission::hasPermission(
            Permissions::PASSWORDS_SHARE,
            $this
        );

        $canShareGroup = Permission::hasPermission(
            Permissions::PASSWORDS_SHARE_GROUP,
            $this
        );

        $canDeleteGroup = Permission::hasPermission(
            Permissions::PASSWORDS_DELETE_GROUP,
            $this
        );

        $canLinkPassword = Permission::hasPermission(
            Permissions::PASSWORDS_DELETE_GROUP,
            $this
        );

        foreach ($result as $row) {
            $isOwner        = in_array($row['id'], $ownerPasswordIds);
            $row['isOwner'] = $isOwner;

            if (in_array($row['id'], $directAccessPasswordIds)) {
                $row['access']    = 'user';
                $row['canShare']  = $canShareOwn;
                $row['canDelete'] = true;
            } else {
                $row['access']    = 'group';
                $row['canShare']  = $canShareGroup;
                $row['canDelete'] = $canDeleteGroup;
            }

            $row['dataType'] = PasswordTypesHandler::getTypeTitle($row['dataType']);

            switch ((int)$row['ownerType']) {
                case Password::OWNER_TYPE_USER:
                    $row['canLink']   = $isOwner;
                    $row['ownerName'] = QUI::getUsers()->get($row['ownerId'])->getName();
                    break;

                case Password::OWNER_TYPE_GROUP:
                    $row['canLink']   = $isOwner && $canLinkPassword;
                    $row['ownerName'] = QUI::getGroups()->get($row['ownerId'])->getName();
                    break;
            }

            $passwords[] = $row;
        }

        // check if passwords are shared
        $passwordIdsSharedWithUsers  = $this->getOwnerPasswordIdsSharedWithUsers();
        $passwordIdsSharedWithGroups = $this->getOwnerPasswordIdsSharedWithGroups();

        // set results to password list
        $securityClassIds = array();

        foreach ($passwords as $k => $row) {
            $row['sharedWithUsers']  = false;
            $row['sharedWithGroups'] = false;

            if (in_array($row['id'], $passwordIdsSharedWithUsers)) {
                $row['sharedWithUsers'] = true;
            }

            if (in_array($row['id'], $passwordIdsSharedWithGroups)) {
                $row['sharedWithGroups'] = true;
            }

            $passwords[$k]      = $row;
            $securityClassIds[] = $row['securityClassId'];
        }

        if (empty($passwords)) {
            return array();
        }

        // get titles of all security classes
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id',
                'title',
                'allowPasswordLinks'
            ),
            'from'   => Tables::securityClasses(),
            'where'  => array(
                'id' => array(
                    'type'  => 'IN',
                    'value' => $securityClassIds
                )
            )
        ));

        foreach ($passwords as $k => $data) {
            foreach ($result as $row) {
                if ($data['securityClassId'] == $row['id']) {
                    $passwords[$k]['securityClassTitle'] = $row['title'];

                    if (!(int)$row['allowPasswordLinks']) {
                        $passwords[$k]['canLink'] = false;
                    }

                    $Password                   = Passwords::get($data['id']);
                    $passwords[$k]['canAccess'] = $Password->hasPasswordAccess($this);

                    continue 2;
                }
            }
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
            'from'   => Tables::usersToPasswordMeta(),
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
            'from'   => Tables::usersToPasswords(),
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
            'from'   => Tables::groupsToPasswords(),
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
     * @param bool $useCache (optional) - get results from cache [default: true]
     * @return array - password IDs
     */
    public function getNonFullyAccessiblePasswordIds(Plugin $AuthPlugin, $useCache = true)
    {
        if (!$AuthPlugin->isRegistered($this)) {
            return array();
        }

        $cname = 'pcsg/gpm/cryptouser/nonfullyaccessiblepasswordids/' . $AuthPlugin->getId();

        if ($useCache !== false) {
            try {
                return QUI\Cache\Manager::get($cname);
            } catch (\Exception $Exception) {
                // nothing, determine ids
            }
        }

        $passwordIds = array();
        $AuthKeyPair = $this->getAuthKeyPair($AuthPlugin);

        // direct access
        $authPluginAccessDirect = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'dataId'
            ),
            'from'   => Tables::usersToPasswords(),
            'where'  => array(
                'userId'    => $this->getId(),
                'keyPairId' => $AuthKeyPair->getId()
            )
        ));

        foreach ($result as $row) {
            $authPluginAccessDirect[$row['dataId']] = true;
        }

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
                'sequry/core',
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
                'sequry/core',
                'exception.cryptouser.rencryptpasswordaccessKey.securityclass.not.eligible',
                array(
                    'userId'             => $this->getId(),
                    'userName'           => $this->getName(),
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

        // delete old access entries
        $DB->delete(
            Tables::usersToPasswords(),
            array(
                'userId' => $this->getId(),
                'dataId' => $passwordId
            )
        );

        /** @var AuthKeyPair $UserAuthKeyPair */
        foreach ($authKeyPairs as $UserAuthKeyPair) {
            try {
                $encryptedPasswordKeyPart = AsymmetricCrypto::encrypt(
                    new HiddenString($passwordKeyParts[$i++]),
                    $UserAuthKeyPair
                );

                $dataAccessEntry = array(
                    'userId'    => $this->getId(),
                    'dataId'    => $passwordId,
                    'dataKey'   => $encryptedPasswordKeyPart,
                    'keyPairId' => $UserAuthKeyPair->getId()
                );

                $dataAccessEntry['MAC'] = MAC::create(
                    new HiddenString(implode('', $dataAccessEntry)),
                    Utils::getSystemKeyPairAuthKey()
                );

                $DB->insert(
                    Tables::usersToPasswords(),
                    $dataAccessEntry
                );
            } catch (\Exception $Exception) {
                QUI\System\Log::addError(
                    'CryptoUser :: reEncryptPasswordAccessKey() :: Error writing password key parts to database: '
                    . $Exception->getMessage()
                );

                throw new QUI\Exception(array(
                    'sequry/core',
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
     * @param CryptoGroup $CryptoGroup
     * @param SecurityClass $SecurityClass
     * @return void
     * @throws QUI\Exception
     */
    public function reEncryptGroupAccessKey(CryptoGroup $CryptoGroup, SecurityClass $SecurityClass)
    {
        if (!$CryptoGroup->isUserInGroup($this)) {
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
                'sequry/core',
                'exception.cryptouser.rencryptpasswordaccessKey.securityclass.not.eligible',
                array(
                    'userId'             => $this->getId(),
                    'userName'           => $this->getName(),
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

        // delete old access entries
        $DB->delete(
            Tables::usersToGroups(),
            array(
                'userId'          => $this->getId(),
                'groupId'         => $CryptoGroup->getId(),
                'securityClassId' => $SecurityClass->getId()
            )
        );

        /** @var AuthKeyPair $UserAuthKeyPair */
        foreach ($authKeyPairs as $UserAuthKeyPair) {
            try {
                $payloadKeyPart = $groupAccessKeyParts[$i++];

                $groupAccessKeyPartEncrypted = AsymmetricCrypto::encrypt(
                    new HiddenString($payloadKeyPart),
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
                $data['MAC'] = MAC::create(
                    new HiddenString(implode('', $data)),
                    Utils::getSystemKeyPairAuthKey()
                );

                $DB->insert(Tables::usersToGroups(), $data);
            } catch (\Exception $Exception) {
                QUI\System\Log::addError(
                    'Error writing group key parts to database: ' . $Exception->getMessage()
                );

                QUI::getDataBase()->delete(
                    Tables::usersToGroups(),
                    array(
                        'userId'          => $this->getId(),
                        'groupId'         => $CryptoGroup->getId(),
                        'securityClassId' => $SecurityClass->getId()
                    )
                );

                throw new QUI\Exception(array(
                    'sequry/core',
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
     * Re-encrypts all cryptographic data the user has access to:
     *
     * - User private keys
     * - User password access keys
     * - User group access keys
     * - Group private keys
     * - Group password access keys
     *
     * @return void
     * @throws QUI\Exception
     */
    public function reEncryptAllKeys()
    {
        set_time_limit(0); // disable php timeout

        $authKeyPairIds = $this->getAuthKeyPairIds();

        // user keys
        /** @var AuthKeyPair $AuthKeyPair */
        foreach ($authKeyPairIds as $authKeyPairId) {
            $AuthKeyPair = Authentication::getAuthKeyPair($authKeyPairId);
            $AuthPlugin  = $AuthKeyPair->getAuthPlugin();
            $DerivedKey  = $AuthPlugin->getDerivedKey($this);
            $privateKey  = $AuthKeyPair->getPrivateKey()->getValue();

            $privateKeyEncrypted = SymmetricCrypto::encrypt(
                $privateKey,
                $DerivedKey
            );

            $macValue = MAC::create(
                new HiddenString(
                    $AuthKeyPair->getPublicKey()->getValue()->getString()
                    . $privateKeyEncrypted
                ),
                Utils::getSystemKeyPairAuthKey()
            );

            QUI::getDataBase()->update(
                Tables::keyPairsUser(),
                array(
                    'privateKey' => $privateKeyEncrypted,
                    'MAC'        => $macValue
                ),
                array(
                    'id' => $AuthKeyPair->getId()
                )
            );
        }

        // group keys
        $cryptoGroups = $this->getCryptoGroups();

        /** @var CryptoGroup $CryptoGroup */
        foreach ($cryptoGroups as $CryptoGroup) {
            $groupSecurityClasses = $CryptoGroup->getSecurityClasses();

            /** @var SecurityClass $SecurityClass */
            foreach ($groupSecurityClasses as $SecurityClass) {
                $this->reEncryptGroupAccessKey($CryptoGroup, $SecurityClass);

                $GroupKeyPair   = $this->getGroupKeyPairDecrypted($CryptoGroup, $SecurityClass);
                $GroupAccessKey = $this->getGroupAccessKey($CryptoGroup, $SecurityClass);

                $groupPrivateKeyEncrypted = SymmetricCrypto::encrypt(
                    $GroupKeyPair->getPrivateKey()->getValue(),
                    $GroupAccessKey
                );

                $data = array(
                    'groupId'         => $CryptoGroup->getId(),
                    'securityClassId' => $SecurityClass->getId(),
                    'publicKey'       => $GroupKeyPair->getPublicKey()->getValue(),
                    'privateKey'      => $groupPrivateKeyEncrypted
                );

                // calculate group key MAC
                $mac = MAC::create(
                    new HiddenString(implode('', $data)),
                    Utils::getSystemKeyPairAuthKey()
                );

                QUI::getDataBase()->update(
                    Tables::keyPairsGroup(),
                    array(
                        'privateKey' => $groupPrivateKeyEncrypted,
                        'MAC'        => $mac
                    ),
                    array(
                        'groupId'         => $CryptoGroup->getId(),
                        'securityClassId' => $SecurityClass->getId()
                    )
                );
            }

            // re-encrypt group password access keys!
            $groupPasswordIds = $CryptoGroup->getPasswordIds();

            foreach ($groupPasswordIds as $groupPasswordId) {
                $CryptoGroup->reEncryptPasswordAccessKey($groupPasswordId);
            }
        }

        // password access keys
        $this->reEncryptAllPasswordAccessKeys();

        // password keys
        $passwordIds = $this->getPasswordIds();

        foreach ($passwordIds as $passwordId) {
            $PasswordAccessKey = $this->getPasswordAccessKey($passwordId);

            $result = QUI::getDataBase()->fetch(array(
                'from'  => Tables::passwords(),
                'where' => array(
                    'id' => $passwordId
                )
            ));

            $pw = current($result);

            $pwContent = SymmetricCrypto::decrypt(
                $pw['cryptoData'],
                $PasswordAccessKey
            );

            $pwContentEncrypted = SymmetricCrypto::encrypt(
                $pwContent,
                $PasswordAccessKey
            );

            $pw['cryptoData'] = $pwContentEncrypted;

            $macFields = SymmetricCrypto::decrypt(
                $pw['MACFields'],
                Utils::getSystemPasswordAuthKey()
            );

            $macFieldsEncrypted = SymmetricCrypto::encrypt(
                $macFields,
                Utils::getSystemPasswordAuthKey()
            );

            $macFields = json_decode($macFields, true);

            $macData = array();

            foreach ($macFields as $field) {
                if (isset($pw[$field])) {
                    $macData[] = $pw[$field];
                } else {
                    $macData[] = null;
                }
            }

            $newMac = MAC::create(
                new HiddenString(implode('', $macData)),
                Utils::getSystemPasswordAuthKey()
            );

            QUI::getDataBase()->update(
                Tables::passwords(),
                array(
                    'cryptoData' => $pwContentEncrypted,
                    'MAC'        => $newMac,
                    'MACFields'  => $macFieldsEncrypted
                ),
                array(
                    'id' => $passwordId
                )
            );
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
            Tables::usersToPasswordMeta(),
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
            'from'  => Tables::usersToPasswordMeta(),
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
     * Create entry in meta data table for a password for this user
     *
     * @param Password $Password - The password the meta table entry is created for
     * @return void
     *
     * @throws QUI\Exception
     */
    public function createMetaTableEntry(Password $Password)
    {
        $metaData = $this->getPasswordMetaData($Password->getId());

        if (!empty($metaData)) {
            return;
        }

        QUI::getDataBase()->insert(
            Tables::usersToPasswordMeta(),
            array(
                'userId'     => $this->id,
                'dataId'     => $Password->getId(),
                'accessDate' => time()
            )
        );
    }

    /**
     * Remove entry in meta data table for a password for this user
     *
     * @param Password $Password
     * @return void
     *
     * @throws QUI\Exception
     */
    public function removeMetaTableEntry(Password $Password)
    {
        $passwordId = $Password->getId();

        QUI::getDataBase()->delete(
            Tables::usersToPasswordMeta(),
            array(
                'userId' => $this->id,
                'dataId' => $passwordId
            )
        );

        if (isset($this->passwordMetaData[$passwordId])) {
            unset($this->passwordMetaData[$passwordId]);
        }
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
            Tables::usersToPasswordMeta(),
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
        // user that are group admins cannot be deleted
        $adminGroups = $this->getAdminGroups();

        if (!empty($adminGroups)) {
            $groups = array();

            foreach ($adminGroups as $CryptoGroup) {
                $groups[] = $CryptoGroup->getName() . ' (#' . $CryptoGroup->getId() . ')';
            }

            throw new Exception(array(
                'sequry/core',
                'exception.cryptouser.delete.group_admins_cannot_be_deleted',
                array(
                    'groups' => implode(', ', $groups)
                )
            ));
        }

        // users can only be deleted by themselves or super users
        $SessionUser = QUI::getUserBySession();

        if ((int)$SessionUser->getId() !== (int)$this->getId()
            && !$SessionUser->isSU()
        ) {
            throw new Exception(array(
                'sequry/core',
                'exception.cryptouser.delete.no.permission'
            ));
        }

        // check if user is last user of any CryptoGroups
        $groups = $this->getCryptoGroups();

        /** @var CryptoGroup $CryptoGroup */
        foreach ($groups as $CryptoGroup) {
            $userCount = (int)$CryptoGroup->countUser();

            if ($userCount <= 1) {
                throw new Exception(array(
                    'sequry/core',
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
            Tables::usersToPasswords(),
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
            Tables::keyPairsUser(),
            array(
                'userId' => $this->getId()
            )
        );

        // delete recovery data
        $DB->delete(
            Tables::recovery(),
            array(
                'userId' => $this->getId()
            )
        );

        // delete password meta data
        $DB->delete(
            Tables::usersToPasswordMeta(),
            array(
                'userId' => $this->getId()
            )
        );

        Events::$triggerUserDeleteConfirm = false;

        parent::delete();
    }

    /**
     * Checks if all pre-requisites are met for this user for basic
     * password manager usage.
     *
     * @return bool
     */
    public function canUsePasswordManager()
    {
        // check if at least one security class is set up
//        $securityClasses = Authentication::getSecurityClassesList();
//
//        if (empty($securityClasses)) {
//            return false;
//        }

        // check if the user has at least registered with one authentication method
        $authKeyPairIds = $this->getAuthKeyPairIds();

        if (empty($authKeyPairIds)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if this user has access to (any) passwords in a given public
     * password category
     *
     * @param int $categoryId
     * @return bool
     */
    public function hasAccessToPasswordsInPublicCategory($categoryId)
    {
        $cacheName = 'sequry/core/publiccategoryaccess/' . $this->id . '/' . $categoryId;

        try {
            return CacheManager::get($cacheName);
        } catch (QUI\Cache\Exception $Exception) {
            // nothing, retrieve fresh information
        }

        $results = $this->getPasswordList(array(
            'categoryId' => $categoryId
        ), true);

        CacheManager::set($cacheName, !empty($results));

        return !empty($results);
    }

    /**
     * Determine if this user has access to (any) passwords in a given public
     * password category
     *
     * @param int $categoryId
     * @return bool
     */
    public function hasAccessToPasswordsInPrivateCategory($categoryId)
    {
        $cacheName = 'sequry/core/privatecategoryaccess/' . $this->id . '/' . $categoryId;

        try {
            return CacheManager::get($cacheName);
        } catch (QUI\Cache\Exception $Exception) {
            // nothing, retrieve fresh information
        }

        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::usersToPasswordMeta(),
            'where' => array(
                'userId'     => $this->id,
                'categories' => array(
                    'type'  => '%LIKE%',
                    'value' => ',' . $categoryId . ','
                )
            ),
            'count' => 1
        ));

        $count = current(current($result));

        CacheManager::set($cacheName, !empty($count));

        return !empty($count);
    }

    /**
     * Checks all user passwords and adds or deletes meta table entries
     *
     * @return void
     */
    public function refreshPasswordMetaTableEntries()
    {
        $metaTbl = Tables::usersToPasswordMeta();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'dataId'
            ),
            'from'   => $metaTbl,
            'where'  => array(
                'userId' => $this->id
            )
        ));

        $passwordIds     = $this->getPasswordIds();
        $metaPasswordIds = array();

        foreach ($result as $row) {
            $metaPasswordIds[$row['dataId']] = true;
        }

        // create missing meta table entries
        foreach ($passwordIds as $passwordId) {
            if (!isset($metaPasswordIds[$passwordId])) {
                $Password = Passwords::get($passwordId);
                $Password->createMetaTableEntry($this);
            }
        }

        // delete old meta table entries
        foreach ($metaPasswordIds as $passwordId => $v) {
            if (!in_array($passwordId, $passwordIds)) {
                QUI::getDataBase()->delete(
                    $metaTbl,
                    array(
                        'userId' => $this->id,
                        'dataId' => $passwordId
                    )
                );
            }
        }
    }

    /**
     * Get all CryptoGroups this User is an admin of
     *
     * @return CryptoGroup[]
     */
    public function getAdminGroups()
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'groupId'
            ),
            'from'   => Tables::groupAdmins(),
            'where'  => array(
                'userId' => $this->getId()
            )
        ));

        $groups = array();

        foreach ($result as $row) {
            $groups[] = CryptoActors::getCryptoGroup($row['groupId']);
        }

        return $groups;
    }

    /**
     * Get list of all pending authorization processes for
     * users that do not yet have full access to a CryptoGroup
     *
     * @param array $searchParams - search options
     * @param bool $count (optional) - get count only
     * @return array|int
     * @throws \Sequry\Core\Exception\Exception
     */
    public function getAdminGroupsUnlockList($searchParams, $count = false)
    {
        $Grid       = new Grid($searchParams);
        $gridParams = $Grid->parseDBParams($searchParams);

        $binds = array();
        $where = array(
            '`userKeyPairId` IS NOT NULL',
            '`groupKey` IS NULL'
        );

        if ($count) {
            $sql = "SELECT COUNT(*)";
        } else {
            $sql = "SELECT `userId`, `groupId`, `userKeyPairId`, `securityClassId`";
        }

        $sql .= " FROM `" . Tables::usersToGroups() . "`";

        // build WHERE query string
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        // ORDER
        if (!empty($searchParams['sortOn'])
        ) {
            $sortOn = Orthos::clear($searchParams['sortOn']);
            $order  = "ORDER BY " . $sortOn;

            if (isset($searchParams['sortBy']) &&
                !empty($searchParams['sortBy'])
            ) {
                $order .= " " . Orthos::clear($searchParams['sortBy']);
            } else {
                $order .= " ASC";
            }

            $sql .= " " . $order;
        } else {
            $sql .= " ORDER BY id DESC";
        }

        // LIMIT
        if (!empty($gridParams['limit'])
            && !$count
        ) {
            $sql .= " LIMIT " . $gridParams['limit'];
        } else {
            if (!$count) {
                $sql .= " LIMIT " . (int)20;
            }
        }

        $Stmt = QUI::getPDO()->prepare($sql);

        // bind search values
        foreach ($binds as $var => $bind) {
            $Stmt->bindValue(':' . $var, $bind['value'], $bind['type']);
        }

        try {
            $Stmt->execute();
            $result = $Stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class . ' :: search() -> ' . $Exception->getMessage()
            );

            return array();
        }

        if ($count) {
            return (int)current(current($result));
        }

        $parsedEntries = array();
        $factorCount   = array();

        foreach ($result as $k => $row) {
            $hash = md5($row['securityClassId'] . $row['groupId'] . $row['userId']);

            if (!isset($factorCount[$hash])) {
                $factorCount[$hash] = 0;
            }

            $factorCount[$hash]++;

            $SecurityClass = Authentication::getSecurityClass($row['securityClassId']);
            $CryptoGroup   = CryptoActors::getCryptoGroup($row['groupId']);
            $CrpytoUser    = CryptoActors::getCryptoUser($row['userId']);

            $row['securityClass'] = $SecurityClass->getAttribute('title') . ' (#' . $SecurityClass->getId() . ')';
            $row['group']         = $CryptoGroup->getName() . ' (#' . $CryptoGroup->getId() . ')';
            $row['userName']      = $CrpytoUser->getName();
            $row['hash']          = $hash;

            $result[$k] = $row;
        }

        foreach ($result as $k => $r) {
            if (isset($parsedEntries[$r['hash']])) {
                unset($result[$k]);
                continue;
            }

            $SecurityClass = Authentication::getSecurityClass($r['securityClassId']);

            if ($factorCount[$r['hash']] < $SecurityClass->getRequiredFactors()) {
                unset($result[$k]);
                continue;
            }

            $parsedEntries[$r['hash']] = true;
        }

        return $result;
    }
}
