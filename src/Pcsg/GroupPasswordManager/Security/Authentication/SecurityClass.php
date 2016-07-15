<?php

namespace Pcsg\GroupPasswordManager\Security\Authentication;

use Monolog\Handler\Curl\Util;
use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
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
     * Authentication plugins this security class uses
     *
     * @var array
     */
    protected $plugins = null;

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
            'from'  => Tables::SECURITY_CLASSES,
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

        $data     = current($result);
        $this->id = $data['id'];

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
     * Authenticates current session user with all authentication plugins associated with this security class
     *
     * @param array $authData - authentication data
     * @return bool
     * @throws QUI\Exception
     */
    public function authenticate($authData)
    {
        if (empty($authData)) {
            // @todo eigenen 401 error code
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.authenticate.authdata.not.given'
            ));
        }

        $plugins = $this->getAuthPlugins();

        /** @var Plugin $AuthPlugin */
        foreach ($plugins as $AuthPlugin) {
            if (!isset($authData[$AuthPlugin->getId()])) {
                // @todo eigenen 401 error code
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.securityclass.authenticate.missing.authdata',
                    array(
                        'authPluginId'    => $AuthPlugin->getId(),
                        'authPluginTitle' => $AuthPlugin->getAttribute('title')
                    )
                ));
            }

            $AuthPlugin->authenticate($authData[$AuthPlugin->getId()]);
        }

        return true;
    }

    /**
     * Checks if current session user is authenticated with all associated security plugin
     *
     * @return bool
     */
    public function isAuthenticated()
    {
        $plugins = $this->getAuthPlugins();

        /** @var Plugin $AuthPlugin */
        foreach ($plugins as $AuthPlugin) {
            if (!$AuthPlugin->isAuthenticated()) {
                return false;
            }
        }

        return true;
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

        $plugins = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'authPluginId'
            ),
            'from'   => Tables::SECURITY_TO_AUTH,
            'where'  => array(
                'securityClassId' => $this->id
            )
        ));

        foreach ($result as $row) {
            $plugins[] = Authentication::getAuthPlugin($row['authPluginId']);
        }

        $this->plugins = $plugins;

        return $this->plugins;
    }

    /**
     * Checks if a user has the necessary key pairs to use this security class
     *
     * @param QUI\Users\User $User
     * @return bool
     */
    public function isUserEligible($User)
    {
        return in_array($User->getId(), $this->getEligibleUserIds());
    }

    /**
     * Checks if a user has the necessary key pairs to use this security class
     *
     * @param QUI\Groups\Group $Group
     * @return bool
     */
    public function isGroupEligible($Group)
    {
        return in_array($Group->getId(), $this->getGroupIds());
    }

    /**
     * Get list of users that are eligible to use password with this security class
     *
     * @return array
     */
    public function getEligibleUserIds()
    {
        $userIds = array();
        $plugins = $this->getAuthPlugins();

        if (empty($plugins)) {
            return $userIds;
        }

        /** @var Plugin $FirstPlugin */
        $FirstPlugin = array_shift($plugins);
        $userIds     = $FirstPlugin->getRegisteredUserIds();

        /** @var Plugin $AuthPlugin */
        foreach ($plugins as $AuthPlugin) {
            $userIds = array_intersect($userIds, $AuthPlugin->getRegisteredUserIds());
        }

        return $userIds;
    }

    /**
     * Get groups that are assigned to this security class
     *
     * @return array
     */
    public function getGroups()
    {
        $groups   = array();
        $groupIds = $this->getGroupIds();

        foreach ($groupIds as $groupId) {
            $groups = CryptoActors::getCryptoUser();
        }
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
            'from'   => Tables::KEYPAIRS_GROUP,
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
     * Search eligible users and/or groups for this security class
     *
     * @param string $search - search term (username / group name)
     * @param string $type - "users" / "groups"
     * @param integer $limit
     * @return array
     */
    public function searchEligibleActors($search, $type, $limit)
    {
        switch ($type) {
            case 'users':
                $actors = $this->searchEligibleUsers($search);
                break;

            case 'groups':
                $actors = $this->searchEligibleGroups($search);
                break;

            default:
                $actors = $this->searchEligibleUsers($search);
                $actors = array_merge(
                    $actors,
                    $this->searchEligibleGroups($search)
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
    protected function searchEligibleUsers($search)
    {
        $actors  = array();
        $userIds = $this->getEligibleUserIds();

        if (empty($userIds)) {
            return $actors;
        }

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id',
                'username'
            ),
            'from'   => 'users',
            'where'  => array(
                'id'       => array(
                    'type'  => 'IN',
                    'value' => $userIds
                ),
                'username' => array(
                    'type'  => '%LIKE%',
                    'value' => $search
                )
            )
        ));

        foreach ($result as $row) {
            $actors[] = array(
                'id'   => $row['id'],
                'name' => $row['username'],
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
    protected function searchEligibleGroups($search)
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
            if (!$this->checkGroupUsersForEligibility($Group)) {
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
     */
    public function edit($data)
    {
        foreach ($data as $k => $v) {
            switch ($k) {
                case 'title':
                case 'description':
                    if (is_string($v)) {
                        $this->setAttribute($k, $v);
                    }
                    break;

                case 'groups':
                    foreach ($v as $groupData) {
                        $Group = QUI::getGroups()->get($groupData['id']);

                        if (!$this->isGroupEligible($Group)) {
                            $this->addCryptoGroup($Group);
                        }
                    }
                    break;
            }
        }

        $this->save();
    }

    /**
     * Saves current settings
     *
     * @return true - on success
     * @throws QUI\Database\Exception
     */
    protected function save()
    {
        QUI::getDataBase()->update(
            Tables::SECURITY_CLASSES,
            array(
                'title'       => $this->getAttribute('title'),
                'description' => $this->getAttribute('description')
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
        // check if any passwords exist with this security class
        $count = QUI::getDataBase()->fetch(
            array(
                'count' => 1,
                'from'  => Tables::PASSWORDS,
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

        // delete securityclass to auth entries
        QUI::getDataBase()->delete(
            Tables::SECURITY_TO_AUTH,
            array(
                'securityClassId' => $this->getId()
            )
        );

        // delete security class entry
        QUI::getDataBase()->delete(
            Tables::SECURITY_CLASSES,
            array(
                'id' => $this->getId()
            )
        );

        return true;
    }

    /**
     * Generate a random keypair for a QUIQQER Group and add it to this security class
     *
     * @param QUI\Groups\Group $Group
     * @return void
     *
     * @throws QUI\Exception
     */
    public function addCryptoGroup($Group)
    {
        // check if group is associated with any other security class
        $result = QUI::getDataBase()->fetch(array(
            'count' => 1,
            'from'  => Tables::KEYPAIRS_GROUP,
            'where' => array(
                'groupId' => $Group->getId()
            )
        ));

        if (current(current($result)) > 0) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.addcryptogroup.otherwise.associated',
                array(
                    'groupId'         => $Group->getId(),
                    'securityClassId' => $this->getId()
                )
            ));
        }

        $users = $Group->getUsers();

        if (empty($users)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.addcryptogroup.no.users',
                array(
                    'groupId'         => $Group->getId(),
                    'securityClassId' => $this->getId()
                )
            ));
        }

        if (!$this->checkGroupUsersForEligibility($Group)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.addcryptogroup.users.not.eligible',
                array(
                    'groupId'         => $Group->getId(),
                    'securityClassId' => $this->getId()
                )
            ));
        }

        // generate key pair and encrypt
        $authPlugins = $this->getAuthPlugins();

        $GroupKeyPair    = AsymmetricCrypto::generateKeyPair();
        $publicGroupKey  = $GroupKeyPair->getPublicKey()->getValue();
        $privateGroupKey = $GroupKeyPair->getPrivateKey()->getValue();

        $PrivateKeyEncryptionKey = SymmetricCrypto::generateKey();

        $privateGroupKeyEncrypted = SymmetricCrypto::encrypt(
            $privateGroupKey,
            $PrivateKeyEncryptionKey
        );

        // insert group key data into database
        $DB = QUI::getDataBase();

        $data = array(
            'groupId'         => $Group->getId(),
            'securityClassId' => $this->getId(),
            'publicKey'       => $publicGroupKey,
            'privateKey'      => $privateGroupKeyEncrypted
        );

        // calculate group key MAC
        $data['MAC'] = MAC::create(implode('', $data), Utils::getSystemKeyPairAuthKey());

        $DB->insert(Tables::KEYPAIRS_GROUP, $data);

        // split group private key encryption key into parts and share with group users
        $privateKeyEncryptionKeyParts = SecretSharing::splitSecret(
            $PrivateKeyEncryptionKey->getValue(),
            count($authPlugins)
        );

        foreach ($users as $userData) {
            $User = CryptoActors::getCryptoUser($userData['id']);
            $i    = 0;

            /** @var Plugin $AuthPlugin */
            foreach ($authPlugins as $AuthPlugin) {
                $AuthKeyPair                          = $User->getAuthKeyPair($AuthPlugin);
                $privateKeyEncryptionKeyPartEncrypted = AsymmetricCrypto::encrypt(
                    $privateKeyEncryptionKeyParts[$i++],
                    $AuthKeyPair
                );

                $data = array(
                    'userId'        => $User->getId(),
                    'userKeyPairId' => $AuthKeyPair->getId(),
                    'groupId'       => $Group->getId(),
                    'groupKey'      => $privateKeyEncryptionKeyPartEncrypted
                );

                // calculate MAC
                $data['MAC'] = MAC::create(implode('', $data), Utils::getSystemKeyPairAuthKey());

                $DB->insert(Tables::USER_TO_GROUPS, $data);
            }
        }
    }

    /**
     * Checks if all users of a group are eligible to use this security class
     *
     * @param QUI\Groups\Group $Group
     *
     * @return bool
     */
    protected function checkGroupUsersForEligibility($Group)
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
}