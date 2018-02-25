<?php

namespace Pcsg\GroupPasswordManager\Security\Authentication;

use Monolog\Handler\Curl\Util;
use ParagonIE\Halite\Symmetric\Crypto;
use Pcsg\GroupPasswordManager\Actors\CryptoGroup;
use Pcsg\GroupPasswordManager\Actors\CryptoUser;
use Pcsg\GroupPasswordManager\Constants\Permissions;
use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Exception\InvalidAuthDataException;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\HiddenString;
use Pcsg\GroupPasswordManager\Security\Keys\AuthKeyPair;
use Pcsg\GroupPasswordManager\Security\Keys\KeyPair;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\SecretSharing;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
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
     * @throws QUI\Exception
     */
    public function __construct($id)
    {
        $id = (int)$id;

        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::securityClasses(),
            'where' => array(
                'id' => $id
            )
        ));

        if (empty($result)) {
            throw new QUI\Exception(
                'Security class #' . $id . ' not found.',
                404
            );
        }

        $data = current($result);

        $this->id                 = $data['id'];
        $this->requiredFactors    = $data['requiredFactors'];
        $this->allowPasswordLinks = $data['allowPasswordLinks'] == 1 ? true : false;

        $this->setAttributes(array(
            'title'       => $data['title'],
            'description' => $data['description']
        ));
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
        $status = array(
            'authenticated' => false,
            'authPlugins'   => array()
        );

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
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.authenticate.authdata.not.given'
            ));
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

            try {
                $AuthPlugin->authenticate($authData[$AuthPlugin->getId()], $CryptoUser);
            } catch (\Exception $Exception) {
                $Exception = new InvalidAuthDataException(array(
                    'pcsg/grouppasswordmanager',
                    'exception.securityclass.authenticate.wrong.authdata',
                    array(
                        'authPluginId'    => $AuthPlugin->getId(),
                        'authPluginTitle' => $AuthPlugin->getAttribute('title')
                    )
                ));

                $Exception->setAttribute('authPluginId', $AuthPlugin->getId());

                throw $Exception;
            }

            $succesfulAuthenticationCount++;

            // On successful authentication, save derived key in session data
            Authentication::saveAuthKey($AuthPlugin->getId(), $AuthPlugin->getDerivedKey());
        }

        if ($succesfulAuthenticationCount < $this->requiredFactors) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.authenticate.insufficient.authentication.count',
                array(
                    'securityClassId' => $this->id,
                    'requiredFactors' => $this->requiredFactors
                )
            ));
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

        throw new InvalidAuthDataException(array());
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
        $ids = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'authPluginId'
            ),
            'from'   => Tables::securityClassesToAuthPlugins(),
            'where'  => array(
                'securityClassId' => $this->id
            )
        ));

        foreach ($result as $row) {
            $ids[] = $row['authPluginId'];
        }

        return $ids;
    }

    /**
     * Get all authentication plugins associated with this class
     *
     * @return array
     */
    public function getAuthPlugins()
    {
        if (!is_null($this->plugins)) {
            return $this->plugins;
        }

        $ids     = $this->getAuthPluginIds();
        $plugins = array();

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
        $result = QUI::getDataBase()->fetch(array(
            'count' => 1,
            'from'  => Tables::securityClassesToAuthPlugins(),
            'where' => array(
                'securityClassId' => $this->id
            )
        ));

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
     */
    public function getEligibleUserIds()
    {
        $userIds         = array();
        $eligibleUserIds = array();
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
        $groups   = array();
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
        $eligibleGroupIds = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'groupId'
            ),
            'from'   => Tables::keyPairsGroup(),
            'where'  => array(
                'securityClassId' => $this->id
            )
        ));

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
        $ids    = array();
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => Tables::passwords(),
            'where'  => array(
                'securityClassId' => $this->id
            )
        ));

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
        $actors  = array();
        $userIds = $this->getEligibleUserIds();

        if (empty($userIds)) {
            return $actors;
        }

        // search users_adress table
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'uid',
                'company'
            ),
            'from'   => QUI::getDBTableName('users_address'),
            'where'  => array(
                'company' => array(
                    'type'  => '%LIKE%',
                    'value' => $search
                ),
                'uid'     => array(
                    'type'  => 'IN',
                    'value' => $userIds
                )
            )
        ));

        $addressUserIds       = array();
        $addressUserCompanies = array();

        foreach ($result as $row) {
            $addressUserIds[]                  = $row['uid'];
            $addressUserCompanies[$row['uid']] = $row['company'];
        }

        // search users table
        $sql   = "SELECT id, username, firstname, lastname";
        $sql   .= " FROM `" . QUI::getDBTableName('users') . "`";
        $where = array();
        $binds = array();

        $where[] = '`id` IN (' . implode(',', $userIds) . ')';

        $whereOr = array();

        // add matches from users_address search
        if (!empty($addressUserIds)) {
            $whereOr[] = '`id` IN (' . implode(',', $addressUserIds) . ')';
        }

        $whereOr[]         = 'username LIKE :username';
        $binds['username'] = array(
            'value' => '%' . $search . '%',
            'type'  => \PDO::PARAM_STR
        );

        $whereOr[]          = 'firstname LIKE :firstname';
        $binds['firstname'] = array(
            'value' => '%' . $search . '%',
            'type'  => \PDO::PARAM_STR
        );

        $whereOr[]         = 'lastname LIKE :lastname';
        $binds['lastname'] = array(
            'value' => '%' . $search . '%',
            'type'  => \PDO::PARAM_STR
        );

        $where[] = '(' . implode(' OR ', $whereOr) . ')';

        $sql .= " WHERE " . implode(" AND ", $where);

        $PDO  = QUI::getDataBase()->getPDO();
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
            QUI\System\Log::addError(
                self::class . ' :: suggestSearchEligibleUsers -> '
                . $Exception->getMessage()
            );

            return array();
        }

        foreach ($result as $row) {
            $userNameParts = array();

            if (!empty($row['firstname'])) {
                $userNameParts[] = $row['firstname'];
            }

            if (!empty($row['lastname'])) {
                $userNameParts[] = $row['lastname'];
            }

            if (isset($addressUserCompanies[$row['id']])) {
                $userNameParts[] = '[' . $addressUserCompanies[$row['id']] . ']';
            }

            if (empty($userNameParts)) {
                $userName = $row['username'];
            } else {
                $userName = implode(' ', $userNameParts) . ' (' . $row['username'] . ')';
            }

            $actors[] = array(
                'id'   => $row['id'],
                'name' => $userName,
                'type' => 'user'
            );
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
        $actors   = array();
        $groupIds = $this->getGroupIds();

        if (empty($groupIds)) {
            return $actors;
        }

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id',
                'name'
            ),
            'from'   => 'groups',
            'where'  => array(
                'id'   => array(
                    'type'  => 'IN',
                    'value' => $groupIds
                ),
                'name' => array(
                    'type'  => '%LIKE%',
                    'value' => $search
                )
            )
        ));

        foreach ($result as $row) {
            $actors[] = array(
                'id'   => $row['id'],
                'name' => $row['name'],
                'type' => 'group'
            );
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
        $groups           = array();
        $allGroups        = QUI::getGroups()->getAllGroups(true);
        $eligibleGroupIds = array();

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

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id',
                'name'
            ),
            'from'   => 'groups',
            'where'  => array(
                'id'   => array(
                    'type'  => 'IN',
                    'value' => $eligibleGroupIds
                ),
                'name' => array(
                    'type'  => '%LIKE%',
                    'value' => $search
                )
            )
        ));

        foreach ($result as $row) {
            $groups[] = array(
                'id'   => $row['id'],
                'name' => $row['name'],
                'type' => 'group'
            );
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
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.create.no.permission'
            ));
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
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.create.no.permission'
            ));
        }

        if (in_array($AuthPlugin->getId(), $this->getAuthPluginIds())) {
            return;
        }

        QUI::getDataBase()->insert(
            Tables::securityClassesToAuthPlugins(),
            array(
                'securityClassId' => $this->id,
                'authPluginId'    => $AuthPlugin->getId()
            )
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
            array(
                'title'              => $this->getAttribute('title'),
                'description'        => $this->getAttribute('description'),
                'allowPasswordLinks' => $this->allowPasswordLinks ? 1 : 0
            ),
            array(
                'id' => $this->getId()
            )
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
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.create.no.permission'
            ));
        }

        $DB = QUI::getDataBase();

        // check if any passwords exist with this security class
        $count = $DB->fetch(
            array(
                'count' => 1,
                'from'  => Tables::passwords(),
                'where' => array(
                    'securityClassId' => $this->getId()
                )
            )
        );

        if (current(current($count)) > 0) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.delete.still.in.use'
            ));
        }

        // delete group keys for security class
        $DB->delete(
            Tables::keyPairsGroup(),
            array(
                'securityClassId' => $this->getId()
            )
        );

        // delete user group access for security class
        $DB->delete(
            Tables::usersToGroups(),
            array(
                'securityClassId' => $this->getId()
            )
        );

        // delete securityclass to auth entries
        $DB->delete(
            Tables::securityClassesToAuthPlugins(),
            array(
                'securityClassId' => $this->getId()
            )
        );

        // delete security class entry
        $DB->delete(
            Tables::securityClasses(),
            array(
                'id' => $this->getId()
            )
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
        $result = $Group->getUsers(array(
            'select' => 'id'
        ));

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
