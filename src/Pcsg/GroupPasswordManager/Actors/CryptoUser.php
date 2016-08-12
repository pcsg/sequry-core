<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Actors\CryptoUser
 */

namespace Pcsg\GroupPasswordManager\Actors;

use Pcsg\GroupPasswordManager\Password;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Authentication\Plugin;
use Pcsg\GroupPasswordManager\Security\Authentication\SecurityClass;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
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
     * Get IDs of all passwords the user owns directly (not via group)
     *
     * @return array - password IDs
     */
    public function getOwnerPasswordIds()
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
        $GroupKeyPairDecrypted = $this->getGroupAccessKey($CryptoGroup);

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

        $passwordKeyDecryptedValue = AsymmetricCrypto::decrypt(
            $data['dataKey'],
            $GroupKeyPairDecrypted
        );

        return new Key($passwordKeyDecryptedValue);
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
    public function getPasswordList($searchParams, $countOnly = false)
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
    public function getNonFullyAccessiblePasswordIds($AuthPlugin)
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
        $authPluginAccessGroup = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'groupId'
            ),
            'from'   => Tables::USER_TO_GROUPS,
            'where'  => array(
                'userId'        => $this->getId(),
                'userKeyPairId' => $AuthKeyPair->getId()
            )
        ));

        foreach ($result as $row) {
            $CryptoGroup      = CryptoActors::getCryptoGroup($row['groupId']);
            $groupPasswordIds = $CryptoGroup->getPasswordIds();

            foreach ($groupPasswordIds as $groupPasswordId) {
                $authPluginAccessGroup[$groupPasswordId] = true;
            }
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

                continue;
            }

            if (!isset($authPluginAccessGroup[$passwordId])) {
                $passwordIds[] = $passwordId;
            }
        }

        $passwordIds = array_unique($passwordIds);

        QUI\Cache\Manager::set($cname, $passwordIds);

        return $passwordIds;
    }

    /**
     * Re-encrypts access key to a password (either directly or via group) with the current
     * number of authentication key pairs the user has registered with, according to the respective
     * security class of a password.
     *
     * @param $passwordId
     */
    public function reEncryptAccessKey($passwordId)
    {
        $passwordIdsDirectAccess = $this->getPasswordIdsDirectAccess();

        if (in_array($passwordId, $passwordIdsDirectAccess)) {
            $this->reEncryptDirectAccessKey($passwordId);
        }

        $accessGroupIds = $this->getGroupIdsByPasswordId($passwordId);

        foreach ($accessGroupIds as $accessGroupId) {
            $this->reEncryptGroupAccessKey($accessGroupId);
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
    protected function reEncryptDirectAccessKey($passwordId)
    {
        $passwordIdsDirectAccess = $this->getPasswordIdsDirectAccess();

        if (!in_array($passwordId, $passwordIdsDirectAccess)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptouser.reencryptdirect.no.direct.access',
                array(
                    'userId'     => $this->getId(),
                    'passwordId' => $passwordId
                )
            ));
        }

        $Password      = Passwords::get($passwordId);
        $PasswordKey   = $Password->getPasswordKey();
        $SecurityClass = Passwords::getSecurityClass($passwordId);

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
                // on error delete password entry
                $DB->delete(
                    Tables::PASSWORDS,
                    array(
                        'id' => $passwordId
                    )
                );

                QUI\System\Log::addError(
                    'CryptoUser :: reEncryptDirectAccessKey() :: Error writing password key parts to database: '
                    . $Exception->getMessage()
                );

                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.crptouser.reencryptkeys.general.error'
                ));
            }
        }
    }

    protected function reEncryptGroupAccessKey($groupId)
    {
        $passwordIdsGroupAccess = $this->getPasswordIdsGroupAccess();

        if (!in_array($groupId, $passwordIdsGroupAccess)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptouser.reencryptgroup.no.group.access',
                array(
                    'userId'  => $this->getId(),
                    'groupId' => $groupId
                )
            ));
        }

        $CryptoGroup    = CryptoActors::getCryptoGroup($groupId);
        $GroupAccessKey = $this->getGroupAccessKey($CryptoGroup);
        $SecurityClass  = $CryptoGroup->getSecurityClass();

        // split key
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
                $groupAccessKeyPartEncrypted = AsymmetricCrypto::encrypt(
                    $groupAccessKeyParts[$i++],
                    $UserAuthKeyPair
                );

                $data = array(
                    'userId'        => $this->getId(),
                    'userKeyPairId' => $UserAuthKeyPair->getId(),
                    'groupId'       => $this->getId(),
                    'groupKey'      => $groupAccessKeyPartEncrypted
                );

                // calculate MAC
                $data['MAC'] = MAC::create(implode('', $data), Utils::getSystemKeyPairAuthKey());

                $DB->insert(Tables::USER_TO_GROUPS, $data);
            } catch (\Exception $Exception) {
                QUI\System\Log::addError(
                    'CryptoUser :: reEncryptGroupAccessKey :: Error writing group key parts to database: '
                    . $Exception->getMessage()
                );

                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptouser.reencryptkeys.general.error'
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
    public function reEncryptAllAccessKeys()
    {
        // re encrypt direct access
        $passwordIdsDirect = $this->getPasswordIdsDirectAccess();

        foreach ($passwordIdsDirect as $passwordId) {
            $this->reEncryptDirectAccessKey($passwordId);
        }

        // re encrypt group access
        $groupIds = $this->getCryptoGroupIds();

        foreach ($groupIds as $groupId) {
            $this->reEncryptGroupAccessKey($groupId);
        }
    }

    /**
     * Delete crypto user permanently
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
                        'groupId' => $CryptoGroup->getId()
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
        $ownerPasswordIds = $this->getOwnerPasswordIds();

        foreach ($ownerPasswordIds as $passwordId) {
            $Password = Passwords::get($passwordId);
            $Password->delete();
        }

        // delete keypairs
        $DB = QUI::getDataBase();

        // delete auth plugin users
        $authPlugins = Authentication::getAuthPlugins();

        /** @var Plugin $AuthPlugin */
        foreach ($authPlugins as $AuthPlugin) {
            $AuthPlugin->deleteUser($this);
        }

        $DB->delete(
            Tables::KEYPAIRS_USER,
            array(
                'userId' => $this->getId()
            )
        );
    }
}
