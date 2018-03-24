<?php

/**
 * This file contains \Sequry\Core\Password
 */

namespace Sequry\Core\Security\Handler;

use Sequry\Core\Actors\CryptoGroup;
use Sequry\Core\Actors\CryptoUser;
use Sequry\Core\Constants\Permissions;
use Sequry\Core\Events;
use Sequry\Core\Exception\Exception;
use Sequry\Core\Security\AsymmetricCrypto;
use Sequry\Core\Security\Authentication\SecurityClass;
use Sequry\Core\Security\HiddenString;
use Sequry\Core\Security\Keys\AuthKeyPair;
use Sequry\Core\Security\MAC;
use Sequry\Core\Security\SecretSharing;
use Sequry\Core\Security\SymmetricCrypto;
use Sequry\Core\Security\Utils;
use QUI;
use Sequry\Core\Constants\Tables;
use QUI\Permissions\Permission as QUIPermissions;
use QUI\Utils\Security\Orthos;

/**
 * Class for for managing system actors - users and groups
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class CryptoActors
{
    /**
     * Crypto users
     *
     * @var array
     */
    protected static $users = array();

    /**
     * Crypto groups
     *
     * @var array
     */
    protected static $groups = array();

    /**
     * Get crypto user
     *
     * @param integer $id (optional) - QUIQQER User ID; if omitted use session user
     * @return CryptoUser
     */
    public static function getCryptoUser($id = null)
    {
        if (is_null($id)) {
            $User = QUI::getUserBySession();
        } else {
            $User = QUI::getUsers()->get($id);
        }

        $userId = $User->getId();

        if (isset(self::$users[$userId])) {
            return self::$users[$userId];
        }

        self::$users[$userId] = new CryptoUser($userId);

        return self::$users[$userId];
    }

    /**
     * Creates a CryptoGroup out of a standard QUIQQER Group, so it can be used
     * in the password management system
     *
     * @param QUI\Groups\Group $Group
     * @param SecurityClass $SecurityClass - The security class that determines how the group key will be encrypted
     * @return CryptoGroup
     *
     * @throws QUI\Exception
     */
    public static function createCryptoGroupKey(
        QUI\Groups\Group $Group,
        SecurityClass $SecurityClass
    ) {
        if (!QUIPermissions::hasPermission(Permissions::GROUP_CREATE)) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptogroup.no.permission'
            ));
        };

        $CryptoGroup = self::getCryptoGroup($Group->getId());

        // check if CryptoGroup already has a key for the given SecurityClass
        if ($CryptoGroup->hasSecurityClass($SecurityClass)) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptogroup.securityclass.already.assigned'
            ));
        }

        // check eligibility for all Group admin users
        /** @var CryptoUser $AdminUser */
        $uneligibleUsers = array();
        $adminUsers      = $CryptoGroup->getAdminUsers();

        if (empty($adminUsers)) {
            throw new Exception(array(
                'sequry/core',
                'exception.cryptoactors.createCryptoGroupKey.group_has_no_admins',
                array(
                    'groupId' => $Group->getId()
                )
            ));
        }

        foreach ($adminUsers as $AdminUser) {
            if (!$SecurityClass->isUserEligible($AdminUser)) {
                $uneligibleUsers[] = $AdminUser->getUsername();
            }
        }

        if (!empty($uneligibleUsers)) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.cryptoactors.addcryptogroup.users.not.eligible',
                array(
                    'groupId'         => $Group->getId(),
                    'securityClassId' => $SecurityClass->getId(),
                    'users'           => implode(', ', $uneligibleUsers)
                )
            ));
        }

        // generate key pair and encrypt group key for security class
        $GroupKeyPair    = AsymmetricCrypto::generateKeyPair();
        $publicGroupKey  = $GroupKeyPair->getPublicKey()->getValue();
        $privateGroupKey = $GroupKeyPair->getPrivateKey()->getValue();
        $GroupAccessKey  = SymmetricCrypto::generateKey();

        $privateGroupKeyEncrypted = SymmetricCrypto::encrypt(
            $privateGroupKey,
            $GroupAccessKey
        );

        // insert group key data into database
        $DB = QUI::getDataBase();

        $data = array(
            'groupId'         => $Group->getId(),
            'securityClassId' => $SecurityClass->getId(),
            'publicKey'       => $publicGroupKey,
            'privateKey'      => $privateGroupKeyEncrypted
        );

        // calculate group key MAC
        $data['MAC'] = MAC::create(
            new HiddenString(implode('', $data)),
            Utils::getSystemKeyPairAuthKey()
        );

        $DB->insert(Tables::keyPairsGroup(), $data);

        // split group access key into parts and share with group users
        $groupAccessKeyParts = SecretSharing::splitSecret(
            $GroupAccessKey->getValue(),
            $SecurityClass->getAuthPluginCount(),
            $SecurityClass->getRequiredFactors()
        );

        // grant access to Group Admin Users
        /** @var CryptoUser $AdminUser */
        foreach ($adminUsers as $AdminUser) {
            $authKeyPairs = $AdminUser->getAuthKeyPairsBySecurityClass($SecurityClass);
            $i            = 0;

            /** @var AuthKeyPair $AuthKeyPair */
            foreach ($authKeyPairs as $AuthKeyPair) {
                $privateKeyEncryptionKeyPartEncrypted = AsymmetricCrypto::encrypt(
                    new HiddenString($groupAccessKeyParts[$i++]),
                    $AuthKeyPair
                );

                $data = array(
                    'userId'          => $AdminUser->getId(),
                    'userKeyPairId'   => $AuthKeyPair->getId(),
                    'securityClassId' => $SecurityClass->getId(),
                    'groupId'         => $Group->getId(),
                    'groupKey'        => $privateKeyEncryptionKeyPartEncrypted
                );

                // calculate MAC
                $data['MAC'] = MAC::create(
                    new HiddenString(implode('', $data)),
                    Utils::getSystemKeyPairAuthKey()
                );

                $DB->insert(Tables::usersToGroups(), $data);
            }
        }

        return $CryptoGroup;
    }

    /**
     * Get crypto user
     *
     * @param integer $id - QUIQQER Group ID
     * @return CryptoGroup
     */
    public static function getCryptoGroup($id)
    {
        if (isset(self::$groups[$id])) {
            return self::$groups[$id];
        }

        self::$groups[$id] = new CryptoGroup($id);

        return self::$groups[$id];
    }

    /**
     * Checks if a group has a registered key pair
     *
     * @param integer $groupId - group ID
     * @return bool
     */
    public static function existsCryptoGroup($groupId)
    {
        $result = QUI::getDataBase()->fetch(array(
            'count' => 1,
            'from'  => Tables::keyPairsGroup(),
            'where' => array(
                'groupId' => $groupId
            )
        ));

        return (int)current(current($result)) > 0;
    }

    /**
     * Search actors
     *
     * @param array $searchParams
     * @param bool $countOnly (optional) - Get result count onnly (ignores LIMIT)
     * @return array
     */
    public static function searchActors($searchParams, $countOnly = false)
    {
        $type = false;

        if (!empty($searchParams['type'])) {
            $type = $searchParams['type'];
        }

        switch ($type) {
            case 'groups':
                $actors = self::searchGroups($searchParams, $countOnly);
                break;

            // users
            default:
                $actors = self::searchUsers($searchParams, $countOnly);
                break;
        }

        return $actors;
    }

    /**
     * Search users
     *
     * @param array $searchParams
     * @param bool $countOnly (optional) - Get result count onnly (ignores LIMIT)
     * @return array|int
     */
    public static function searchUsers($searchParams, $countOnly = false)
    {
        $PDO   = QUI::getDataBase()->getPDO();
        $binds = array();
        $where = array();

        $Grid       = new QUI\Utils\Grid($searchParams);
        $gridParams = $Grid->parseDBParams($searchParams);

        $eligibleUserIds = false;

        if (!empty($searchParams['securityClassIds'])
            && is_array($searchParams['securityClassIds'])) {
            $eligibleUserIds = array();

            foreach ($searchParams['securityClassIds'] as $securityClassId) {
                $SecurityClass     = Authentication::getSecurityClass((int)$securityClassId);
                $eligibleUserIds[] = $SecurityClass->getEligibleUserIds();
            }

            if (count($eligibleUserIds) > 1) {
                $eligibleUserIds = call_user_func_array('array_intersect', $eligibleUserIds);
            } else {
                $eligibleUserIds = current($eligibleUserIds);
            }

            if (!empty($searchParams['eligibleOnly']) && !empty($eligibleUserIds)) {
                $where[] = 'users.`id` IN (' . implode(',', $eligibleUserIds) . ')';
            }
        }

        if (!empty($searchParams['filterActorIds'])) {
            $filterActorIds = array();

            foreach ($searchParams['filterActorIds'] as $actorId) {
                if (mb_strpos($actorId, 'u') === 0) {
                    $filterActorIds[] = (int)mb_substr($actorId, 1);
                }
            }

            if (!empty($filterActorIds)) {
                $where[] = 'users.`id` NOT IN (' . implode(',', $filterActorIds) . ')';
            }
        }

        if ($countOnly) {
            $sql = "SELECT COUNT(*)";
        } else {
            $selectFields = array(
//                'keypairds.`id`',
                'users.`id`',
                'users.`firstname`',
                'users.`lastname`',
                'users.`username`'
            );

            $sql = "SELECT " . implode(',', $selectFields);
        }

        // JOIN user access meta table with password data table
//        $sql .= " FROM `" . Tables::keyPairsUser() . "` keypairs, ";
        $sql .= "FROM `" . QUI::getDBTableName('users') . "` users";

//        $where[] = 'keypairs.`userId` = users.`id`';

        if (!empty($searchParams['search'])
        ) {
            $searchTerm = trim($searchParams['search']);
            $whereOR    = array(
                'users.`firstname` LIKE :search',
                'users.`lastname` LIKE :search',
                'users.`username` LIKE :search'
            );

            $binds['search'] = array(
                'value' => '%' . $searchTerm . '%',
                'type'  => \PDO::PARAM_STR
            );

            if (!empty($whereOR)) {
                $where[] = '(' . implode(' OR ', $whereOR) . ')';
            }
        }

        // build WHERE query string
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $orderFields = array();

        // Table column sort
        if (!empty($searchParams['sortOn'])) {
            $orderPrefix = 'users.`';
            $sortOn      = Orthos::clear($searchParams['sortOn']);

            switch ($sortOn) {
                case 'eligible':
                    break;

                default:
                    $order = $orderPrefix . $sortOn . '`';

                    if (isset($searchParams['sortBy']) &&
                        !empty($searchParams['sortBy'])
                    ) {
                        $order .= " " . Orthos::clear($searchParams['sortBy']);
                    } else {
                        $order .= " ASC";
                    }

                    $orderFields[] = $order;
            }
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

        // fetch all users
        try {
            $Stmt->execute();
            $result = $Stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return array();
        }

        if ($countOnly) {
            return (int)current(current($result));
        }

        $users = array();

        foreach ($result as $row) {
            if (isset($users[$row['id']])) {
                continue;
            }

            $nameParts = array();

            if (!empty($row['firstname'])
                && !empty($row['lastname'])
            ) {
                $nameParts[] = $row['firstname'];
                $nameParts[] = $row['lastname'];
                $nameParts[] = '(' . $row['username'] . ')';
            } else {
                $nameParts[] = $row['username'];
            }

            $userEntry = array(
                'id'       => $row['id'],
                'name'     => implode(' ', $nameParts),
                'eligible' => true
            );

            if ($eligibleUserIds !== false) {
                $userEntry['eligible'] = in_array($userEntry['id'], $eligibleUserIds);
            }

            $users[$userEntry['id']] = $userEntry;
        }

        return array_values($users);
    }

    /**
     * Search groups
     *
     * @param array $searchParams
     * @param bool $countOnly (optional) - Get result count onnly (ignores LIMIT)
     * @return array
     */
    public static function searchGroups($searchParams, $countOnly = false)
    {
        $PDO   = QUI::getDataBase()->getPDO();
        $binds = array();
        $where = array();

        $eligibleGroupIds = false;

        if (!empty($searchParams['securityClassIds'])
            && is_array($searchParams['securityClassIds'])) {
            $eligibleGroupIds = array();

            foreach ($searchParams['securityClassIds'] as $securityClassId) {
                $SecurityClass      = Authentication::getSecurityClass((int)$securityClassId);
                $eligibleGroupIds[] = $SecurityClass->getGroupIds();
            }

            if (count($eligibleGroupIds) > 1) {
                $eligibleGroupIds = call_user_func_array('array_intersect', $eligibleGroupIds);
            } else {
                $eligibleGroupIds = current($eligibleGroupIds);
            }

            if (!empty($searchParams['eligibleOnly']) && !empty($eligibleGroupIds)) {
                $where[] = 'groups.`id` IN (' . implode(',', $eligibleGroupIds) . ')';
            }
        }

        if (!empty($searchParams['filterActorIds'])) {
            $filterActorIds = array();

            foreach ($searchParams['filterActorIds'] as $actorId) {
                if (mb_strpos($actorId, 'g') === 0) {
                    $filterActorIds[] = (int)mb_substr($actorId, 1);
                }
            }

            if (!empty($filterActorIds)) {
                $where[] = 'groups.`id` NOT IN (' . implode(',', $filterActorIds) . ')';
            }
        }

        $Grid       = new QUI\Utils\Grid($searchParams);
        $gridParams = $Grid->parseDBParams($searchParams);

        if ($countOnly) {
            $sql = "SELECT COUNT(*)";
        } else {
            $selectFields = array(
//                'keypairds.`id`',
                'groups.`id`',
                'groups.`name`'
            );

            $sql = "SELECT " . implode(',', $selectFields);
        }

        // JOIN user access meta table with password data table
//        $sql .= " FROM `" . Tables::keyPairsGroup() . "` keypairs, ";
        $sql .= " FROM `" . QUI::getDBTableName('groups') . "` groups";

//        $where[] = 'keypairs.`groupId` = groups.`id`';
//        $where[] = 'data.`id` IN (' . implode(',', $passwordIds) . ')';

        if (!empty($searchParams['search'])
        ) {
            $searchTerm = trim($searchParams['search']);
            $whereOR    = array(
                'groups.`name` LIKE :search'
            );

            $binds['search'] = array(
                'value' => '%' . $searchTerm . '%',
                'type'  => \PDO::PARAM_STR
            );

            if (!empty($whereOR)) {
                $where[] = '(' . implode(' OR ', $whereOR) . ')';
            }
        }

        // build WHERE query string
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $orderFields = array();

        // Table column sort
        if (!empty($searchParams['sortOn'])) {
            $orderPrefix = 'groups.`';
            $sortOn      = Orthos::clear($searchParams['sortOn']);

            switch ($sortOn) {
                case 'eligible':
                    break;

                default:
                    $order = $orderPrefix . $sortOn . '`';

                    if (isset($searchParams['sortBy']) &&
                        !empty($searchParams['sortBy'])
                    ) {
                        $order .= " " . Orthos::clear($searchParams['sortBy']);
                    } else {
                        $order .= " ASC";
                    }

                    $orderFields[] = $order;
            }
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

        // fetch all users
        try {
            $Stmt->execute();
            $result = $Stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return array();
        }

        if ($countOnly) {
            return (int)current(current($result));
        }

        $groups = array();

        foreach ($result as $row) {
            if (isset($groups[$row['id']])) {
                continue;
            }

            $groupEntry = array(
                'id'       => $row['id'],
                'name'     => $row['name'],
                'eligible' => true
            );

            if ($eligibleGroupIds !== false) {
                $groupEntry['eligible'] = in_array($groupEntry['id'], $eligibleGroupIds);
            }

            $groups[$groupEntry['id']] = $groupEntry;
        }

        return array_values($groups);
    }
}
