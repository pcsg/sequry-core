<?php

namespace Sequry\Core\Security\Authentication;

use Monolog\Handler\Curl\Util;
use ParagonIE\Halite\Symmetric\Crypto;
use Sequry\Core\Actors\CryptoGroup;
use Sequry\Core\Actors\CryptoUser;
use Sequry\Core\Constants\Permissions;
use Sequry\Core\Constants\Tables;
use Sequry\Core\Exception\Exception;
use Sequry\Core\Exception\InvalidAuthDataException;
use Sequry\Core\Security\AsymmetricCrypto;
use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Security\HiddenString;
use Sequry\Core\Security\Keys\AuthKeyPair;
use Sequry\Core\Security\Keys\KeyPair;
use Sequry\Core\Security\MAC;
use Sequry\Core\Security\SecretSharing;
use Sequry\Core\Security\SymmetricCrypto;
use Sequry\Core\Security\Utils;
use QUI;

/**
 * This class is an internal represantion of an external authentication plugin
 */
class SecurityClass extends QUI\QDOM
{
    /**
     * ID of authentication plugin
     *
     * @var integer
     */
    protected $id = null;

    /**
     * Number of authentication factors that are required to access data of this security class
     *
     * @var null
     */
    protected $requiredFactors = null;

    /**
     * Authentication plugins this security class uses
     *
     * @var array
     */
    protected $plugins = null;

    /**
     * Are PasswordLinks allowed?
     *
     * @var bool
     */
    protected $allowPasswordLinks;

    /**
     * AuthPlugin constructor.
     *
     * @param integer $id - authentication plugin id
     * @throws \Sequry\Core\Exception\Exception
     */
    public function __construct($id)
    {
        $id = (int)$id;

        $result = QUI::getDataBase()->fetch([
            'from'  => Tables::securityClasses(),
            'where' => [
                'id' => $id
            ]
        ]);

        if (empty($result)) {
            throw new Exception(
                'Security class #'.$id.' not found.',
                404
            );
        }

        $data = current($result);

        $this->id                 = $data['id'];
        $this->requiredFactors    = $data['requiredFactors'];
        $this->allowPasswordLinks = $data['allowPasswordLinks'] == 1 ? true : false;

        $this->setAttributes([
            'title'       => $data['title'],
            'description' => $data['description']
        ]);
    }

    /**
     * Get ID of this plugin
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Checks the auth status of every auth plugin of this security class
     *
     * @return array
     */
    public function getAuthStatus()
    {
        $status = [
            'authenticated' => false,
            'authPlugins'   => []
        ];

        $authCount = 0;

        /** @var Plugin $AuthPlugin */
        foreach ($this->getAuthPlugins() as $AuthPlugin) {
            if ($AuthPlugin->isAuthenticated()) {
                $status['authPlugins'][$AuthPlugin->getId()] = true;
                $authCount++;
            } else {
                $status['authPlugins'][$AuthPlugin->getId()] = false;
            }
        }

        $status['authenticated'] = $authCount >= $this->requiredFactors;

        return $status;
    }

    /**
     * Authenticates current session user with all authentication plugins associated with this security class
     *
     * @param array $authData - authentication data
     * @param CryptoUser $CryptoUser (optional) - if omitted, use session user
     * @return bool
     * @throws QUI\Exception
     */
    public function authenticate($authData, $CryptoUser = null)
    {
        if ($this->isAuthenticated()) {
            return true;
        }

        if (empty($authData)
            || !is_array($authData)
        ) {
            // @todo eigenen 401 error code
            throw new QUI\Exception([
                'sequry/core',
                'exception.securityclass.authenticate.authdata.not.given'
            ]);
        }

        if (is_null($CryptoUser)) {
            $CryptoUser = CryptoActors::getCryptoUser();
        }

        if (QUI::getUserBySession()->getId() === $CryptoUser->getId()
            && isset($authData['sessioncache'])
            && $authData['sessioncache']
        ) {
            Authentication::$sessionCache = true;
        }

        $plugins                      = $this->getAuthPlugins();
        $succesfulAuthenticationCount = 0;

        /** @var Plugin $AuthPlugin */
        foreach ($plugins as $AuthPlugin) {
            if ($AuthPlugin->isAuthenticated($CryptoUser)) {
                $succesfulAuthenticationCount++;
                continue;
            }

            if (empty($authData[$AuthPlugin->getId()])) {
                continue;
            }

            $AuthPlugin->authenticate($authData[$AuthPlugin->getId()], $CryptoUser);
            $succesfulAuthenticationCount++;

            // On successful authentication, save derived key in session data
            Authentication::saveAuthKey($AuthPlugin->getId(), $AuthPlugin->getDerivedKey());
        }

        if ($succesfulAuthenticationCount < $this->requiredFactors) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.securityclass.authenticate.insufficient.authentication.count',
                [
                    'securityClassId' => $this->id,
                    'requiredFactors' => $this->requiredFactors
                ]
            ]);
        }

        return true;
    }

    /**
     * Checks if a user is authenticated for this security class
     *
     * @param CryptoUser $CryptoUser (optional) - if omitted, use session user
     * @return void
     *
     * @throws InvalidAuthDataException
     */
    public function checkAuthentication($CryptoUser = null)
    {
        if (is_null($CryptoUser)) {
            $CryptoUser = CryptoActors::getCryptoUser();
        }

        $plugins     = $this->getAuthPlugins();
        $isAuthCount = 0;

        /** @var Plugin $AuthPlugin */
        foreach ($plugins as $AuthPlugin) {
            if ($AuthPlugin->isAuthenticated($CryptoUser)) {
                $isAuthCount++;
            }
        }

        if ($isAuthCount >= $this->requiredFactors) {
            return;
        }

        throw new InvalidAuthDataException([]);
    }

    /**
     * Checks if a user is authenticated with all associated authentication plugins
     *
     * @param CryptoUser $CryptoUser (optional) - if omitted, use session user
     * @return bool
     */
    public function isAuthenticated($CryptoUser = null)
    {
        if (is_null($CryptoUser)) {
            $CryptoUser = CryptoActors::getCryptoUser();
        }

        $plugins     = $this->getAuthPlugins();
        $isAuthCount = 0;

        /** @var Plugin $AuthPlugin */
        foreach ($plugins as $AuthPlugin) {
            if ($AuthPlugin->isAuthenticated($CryptoUser)) {
                $isAuthCount++;
            }
        }

        if ($isAuthCount < $this->requiredFactors) {
            return false;
        }

        return true;
    }

    /**
     * Get IDs of all authentication plugins associated with this class
     *
     * @return array
     */
    public function getAuthPluginIds()
    {
        $ids = [];

        $result = QUI::getDataBase()->fetch([
            'select' => [
                'authPluginId'
            ],
            'from'   => Tables::securityClassesToAuthPlugins(),
            'where'  => [
                'securityClassId' => $this->id
            ]
        ]);

        foreach ($result as $row) {
            $ids[] = $row['authPluginId'];
        }

        return $ids;
    }

    /**
     * Get all authentication plugins associated with this class
     *
     * @return Plugin[]
     * @throws QUI\Exception
     */
    public function getAuthPlugins()
    {
        if (!is_null($this->plugins)) {
            return $this->plugins;
        }

        $ids     = $this->getAuthPluginIds();
        $plugins = [];

        foreach ($ids as $id) {
            $plugins[] = Authentication::getAuthPlugin($id);
        }

        $this->plugins = $plugins;

        return $this->plugins;
    }

    /**
     * Get number of authentication plugins that are associated with this security class
     *
     * @return integer
     */
    public function getAuthPluginCount()
    {
        $result = QUI::getDataBase()->fetch([
            'count' => 1,
            'from'  => Tables::securityClassesToAuthPlugins(),
            'where' => [
                'securityClassId' => $this->id
            ]
        ]);

        return current(current($result));
    }

    /**
     * Checks if a user has the necessary key pairs to use this security class
     *
     * @param QUI\Users\User|int $User - QUI\Users\User or user Id
     * @return bool
     */
    public function isUserEligible($User)
    {
        if ($User instanceof QUI\Users\User) {
            $User = $User->getId();
        }

        return in_array((int)$User, $this->getEligibleUserIds());
    }

    /**
     * Checks if a group has the necessary key pairs to use this security class
     *
     * @param QUI\Groups\Group|int $Group - QUI\Groups\Group or group id
     * @return bool
     */
    public function isGroupEligible($Group)
    {
        if ($Group instanceof QUI\Groups\Group) {
            $Group = $Group->getId();
        }

        return in_array((int)$Group, $this->getGroupIds());
    }

    /**
     * Get list of users that are eligible to use password with this security class
     *
     * @return array
     * @throws QUI\Exception
     */
    public function getEligibleUserIds()
    {
        $userIds         = [];
        $eligibleUserIds = [];
        $plugins         = $this->getAuthPlugins();

        if (empty($plugins)) {
            return $eligibleUserIds;
        }

        // get all registered user ids of associated authentication plugins
        /** @var Plugin $AuthPlugin */
        foreach ($plugins as $AuthPlugin) {
            $registeredUserIds = $AuthPlugin->getRegisteredUserIds();

            foreach ($registeredUserIds as $userId) {
                if (!isset($userIds[$userId])) {
                    $userIds[$userId] = 0;
                }

                $userIds[$userId]++;
            }
        }

        // filter users that are registered with less than the necessary number of authentication plugins
        foreach ($userIds as $userId => $registeredAuthFactors) {
            if ($registeredAuthFactors < $this->requiredFactors) {
                continue;
            }

            $eligibleUserIds[] = $userId;
        }

        return $eligibleUserIds;
    }

    /**
     * Get groups that are assigned to this security class
     *
     * @return array - groups as objects
     */
    public function getGroups()
    {
        $groups   = [];
        $groupIds = $this->getGroupIds();

        foreach ($groupIds as $groupId) {
            $groups[] = CryptoActors::getCryptoGroup($groupId);
        }

        return $groups;
    }

    /**
     * Get ids of groups that are assigned to this security class
     *
     * @return array
     */
    public function getGroupIds()
    {
        $eligibleGroupIds = [];

        $result = QUI::getDataBase()->fetch([
            'select' => [
                'groupId'
            ],
            'from'   => Tables::keyPairsGroup(),
            'where'  => [
                'securityClassId' => $this->id
            ]
        ]);

        foreach ($result as $row) {
            $eligibleGroupIds[] = $row['groupId'];
        }

        return $eligibleGroupIds;
    }

    /**
     * Get IDs of all passwords that are associated with this security class
     *
     * @return array
     */
    public function getPasswordIds()
    {
        $ids    = [];
        $result = QUI::getDataBase()->fetch([
            'select' => [
                'id'
            ],
            'from'   => Tables::passwords(),
            'where'  => [
                'securityClassId' => $this->id
            ]
        ]);

        foreach ($result as $row) {
            $ids[] = $row['id'];
        }

        return $ids;
    }

    /**
     * Suggest search eligible users and/or groups for this security class
     *
     * @param string $search - search term (username / group name)
     * @param string $type - "users" / "groups"
     * @param integer $limit
     * @return array
     */
    public function suggestSearchEligibleActors($search, $type, $limit)
    {
        switch ($type) {
            case 'users':
                $actors = $this->suggestSearchEligibleUsers($search);
                break;

            case 'groups':
                $actors = $this->suggestSearchEligibleGroups($search);
                break;

            default:
                $actors = $this->suggestSearchEligibleUsers($search);
                $actors = array_merge(
                    $actors,
                    $this->suggestSearchEligibleGroups($search)
                );
        }

        return array_slice($actors, 0, $limit);
    }

    /**
     * Searches eligible users for this security class via search term
     *
     * @param string $search
     * @return array
     */
    protected function suggestSearchEligibleUsers($search)
    {
        $actors  = [];
        $userIds = $this->getEligibleUserIds();

        if (empty($userIds)) {
            return $actors;
        }

        // search users_adress table
        $result = QUI::getDataBase()->fetch([
            'select' => [
                'uid',
                'company'
            ],
            'from'   => QUI::getDBTableName('users_address'),
            'where'  => [
                'company' => [
                    'type'  => '%LIKE%',
                    'value' => $search
                ],
                'uid'     => [
                    'type'  => 'IN',
                    'value' => $userIds
                ]
            ]
        ]);

        $addressUserIds       = [];
        $addressUserCompanies = [];

        foreach ($result as $row) {
            $addressUserIds[]                  = $row['uid'];
            $addressUserCompanies[$row['uid']] = $row['company'];
        }

        // search users table
        $sql   = "SELECT id, username, firstname, lastname";
        $sql   .= " FROM `".QUI::getDBTableName('users')."`";
        $where = [];
        $binds = [];

        $where[] = '`id` IN ('.implode(',', $userIds).')';

        $whereOr = [];

        // add matches from users_address search
        if (!empty($addressUserIds)) {
            $whereOr[] = '`id` IN ('.implode(',', $addressUserIds).')';
        }

        $whereOr[]         = 'username LIKE :username';
        $binds['username'] = [
            'value' => '%'.$search.'%',
            'type'  => \PDO::PARAM_STR
        ];

        $whereOr[]          = 'firstname LIKE :firstname';
        $binds['firstname'] = [
            'value' => '%'.$search.'%',
            'type'  => \PDO::PARAM_STR
        ];

        $whereOr[]         = 'lastname LIKE :lastname';
        $binds['lastname'] = [
            'value' => '%'.$search.'%',
            'type'  => \PDO::PARAM_STR
        ];

        $where[] = '('.implode(' OR ', $whereOr).')';

        $sql .= " WHERE ".implode(" AND ", $where);

        $PDO  = QUI::getDataBase()->getPDO();
        $Stmt = $PDO->prepare($sql);

        // bind search values
        foreach ($binds as $var => $bind) {
            $Stmt->bindValue(':'.$var, $bind['value'], $bind['type']);
        }

        // fetch information for all corresponding passwords
        try {
            $Stmt->execute();
            $result = $Stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class.' :: suggestSearchEligibleUsers -> '
                .$Exception->getMessage()
            );

            return [];
        }

        foreach ($result as $row) {
            $userNameParts = [];

            if (!empty($row['firstname'])) {
                $userNameParts[] = $row['firstname'];
            }

            if (!empty($row['lastname'])) {
                $userNameParts[] = $row['lastname'];
            }

            if (isset($addressUserCompanies[$row['id']])) {
                $userNameParts[] = '['.$addressUserCompanies[$row['id']].']';
            }

            if (empty($userNameParts)) {
                $userName = $row['username'];
            } else {
                $userName = implode(' ', $userNameParts).' ('.$row['username'].')';
            }

            $actors[] = [
                'id'   => $row['id'],
                'name' => $userName,
                'type' => 'user'
            ];
        }

        return $actors;
    }

    /**
     * Searches eligible groups for this security class via search term
     *
     * @param string $search
     * @return array
     */
    protected function suggestSearchEligibleGroups($search)
    {
        $actors   = [];
        $groupIds = $this->getGroupIds();

        if (empty($groupIds)) {
            return $actors;
        }

        $result = QUI::getDataBase()->fetch([
            'select' => [
                'id',
                'name'
            ],
            'from'   => 'groups',
            'where'  => [
                'id'   => [
                    'type'  => 'IN',
                    'value' => $groupIds
                ],
                'name' => [
                    'type'  => '%LIKE%',
                    'value' => $search
                ]
            ]
        ]);

        foreach ($result as $row) {
            $actors[] = [
                'id'   => $row['id'],
                'name' => $row['name'],
                'type' => 'group'
            ];
        }

        return $actors;
    }

    /**
     * Searches groups that can be added to the security class
     *
     * @param string $search
     * @param integer $limit
     *
     * @return array
     */
    public function searchGroupsToAdd($search, $limit)
    {
        $groups           = [];
        $allGroups        = QUI::getGroups()->getAllGroups(true);
        $eligibleGroupIds = [];

        /** @var QUI\Groups\Group $Group */
        foreach ($allGroups as $Group) {
            if (!$this->areGroupUsersEligible($Group)) {
                continue;
            }

            $eligibleGroupIds[] = $Group->getId();
        }

        if (empty($eligibleGroupIds)) {
            return $groups;
        }

        $result = QUI::getDataBase()->fetch([
            'select' => [
                'id',
                'name'
            ],
            'from'   => 'groups',
            'where'  => [
                'id'   => [
                    'type'  => 'IN',
                    'value' => $eligibleGroupIds
                ],
                'name' => [
                    'type'  => '%LIKE%',
                    'value' => $search
                ]
            ]
        ]);

        foreach ($result as $row) {
            $groups[] = [
                'id'   => $row['id'],
                'name' => $row['name'],
                'type' => 'group'
            ];
        }

        return $groups;
    }

    /**
     * Edits title and/or description of a security class
     *
     * @param array $data
     * @return void
     *
     * @throws QUI\Exception
     */
    public function edit($data)
    {
        if (!QUI\Permissions\Permission::hasPermission(Permissions::SECURITY_CLASS_EDIT)) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.securityclass.create.no.permission'
            ]);
        }

        foreach ($data as $k => $v) {
            switch ($k) {
                case 'title':
                case 'description':
                    if (is_string($v)) {
                        $this->setAttribute($k, $v);
                    }
                    break;

                case 'newAuthPluginIds':
                    foreach ($v as $authPluginId) {
                        try {
                            $AuthPlugin = Authentication::getAuthPlugin((int)$authPluginId);
                            $this->addAuthPlugin($AuthPlugin);
                        } catch (\Exception $Exception) {
                            // nothing, ignore plugin id
                        }
                    }

                    break;

                case 'allowPasswordLinks':
                    $this->allowPasswordLinks = boolval($v);
                    break;
            }
        }

        $this->save();
    }

    /**
     * Add an authentication plugin to this security class (CAN NOT BE REMOVED LATER!)
     *
     * @param Plugin $AuthPlugin
     * @return void
     *
     * @throws QUI\Exception
     */
    public function addAuthPlugin($AuthPlugin)
    {
        if (!QUI\Permissions\Permission::hasPermission(Permissions::SECURITY_CLASS_EDIT)) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.securityclass.create.no.permission'
            ]);
        }

        if (in_array($AuthPlugin->getId(), $this->getAuthPluginIds())) {
            return;
        }

        QUI::getDataBase()->insert(
            Tables::securityClassesToAuthPlugins(),
            [
                'securityClassId' => $this->id,
                'authPluginId'    => $AuthPlugin->getId()
            ]
        );
    }

    /**
     * Saves current settings
     *
     * @return void
     * @throws QUI\Database\Exception
     */
    protected function save()
    {
        QUI::getDataBase()->update(
            Tables::securityClasses(),
            [
                'title'              => $this->getAttribute('title'),
                'description'        => $this->getAttribute('description'),
                'allowPasswordLinks' => $this->allowPasswordLinks ? 1 : 0
            ],
            [
                'id' => $this->getId()
            ]
        );
    }

    /**
     * Permanently deletes this security class
     *
     * @return true - if successful
     *
     * @throws QUI\Exception
     */
    public function delete()
    {
        if (!QUI\Permissions\Permission::hasPermission(Permissions::SECURITY_CLASS_EDIT)) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.securityclass.create.no.permission'
            ]);
        }

        $DB = QUI::getDataBase();

        // check if any passwords exist with this security class
        $count = $DB->fetch(
            [
                'count' => 1,
                'from'  => Tables::passwords(),
                'where' => [
                    'securityClassId' => $this->getId()
                ]
            ]
        );

        if (current(current($count)) > 0) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.securityclass.delete.still.in.use'
            ]);
        }

        // delete group keys for security class
        $DB->delete(
            Tables::keyPairsGroup(),
            [
                'securityClassId' => $this->getId()
            ]
        );

        // delete user group access for security class
        $DB->delete(
            Tables::usersToGroups(),
            [
                'securityClassId' => $this->getId()
            ]
        );

        // delete securityclass to auth entries
        $DB->delete(
            Tables::securityClassesToAuthPlugins(),
            [
                'securityClassId' => $this->getId()
            ]
        );

        // delete security class entry
        $DB->delete(
            Tables::securityClasses(),
            [
                'id' => $this->getId()
            ]
        );

        return true;
    }

    /**
     * Return number of required authentication modules to access passwords of this security calss
     *
     * @return integer
     */
    public function getRequiredFactors()
    {
        return $this->requiredFactors;
    }

    /**
     * Checks if all users of a group are eligible to use this security class
     *
     * @param QUI\Groups\Group $Group
     *
     * @return bool
     */
    public function areGroupUsersEligible($Group)
    {
        $result = $Group->getUsers([
            'select' => 'id'
        ]);

        if (empty($result)) {
            return false;
        }

        foreach ($result as $row) {
            $User = QUI::getUsers()->get($row['id']);

            if (!$this->isUserEligible($User)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Are PasswordLinks allowed for this SecurityClass?
     *
     * @return bool
     */
    public function isPasswordLinksAllowed()
    {
        return $this->allowPasswordLinks;
    }
}
