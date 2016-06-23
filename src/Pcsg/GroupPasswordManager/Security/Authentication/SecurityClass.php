<?php

namespace Pcsg\GroupPasswordManager\Security\Authentication;

use ParagonIE\Halite\Contract\SymmetricKeyCryptoInterface;
use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\CryptoUser;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Interfaces\iAuthPlugin;
use Pcsg\GroupPasswordManager\Security\MAC;
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
        return in_array($Group->getId(), $this->getEligibleGroupIds());
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
     * Get list of ids of groups that contains users that are eligible to use password with with security class
     *
     * @return array
     */
    public function getEligibleGroupIds()
    {
        $groups           = QUI::getGroups()->getAllGroups(true);
        $eligibleUserIds  = $this->getEligibleUserIds();
        $eligibleGroupIds = array();

        /** @var QUI\Groups\Group $Group */
        foreach ($groups as $Group) {
            $result = $Group->getUsers(array(
                'select' => 'id'
            ));

            $groupUserIds = array();

            foreach ($result as $row) {
                $groupUserIds[] = $row['id'];
            }

            if (!empty(array_diff($groupUserIds, $eligibleUserIds))) {
                continue;
            }

            $eligibleGroupIds[] = $Group->getId();
        }

        return $eligibleGroupIds;
    }
}