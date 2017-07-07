<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Password
 */

namespace Pcsg\GroupPasswordManager\Security\Handler;

use Composer\Cache;
use Pcsg\GpmAuthPassword\AuthPlugin;
use Pcsg\GroupPasswordManager\Constants\Permissions;
use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Actors\CryptoUser;
use Pcsg\GroupPasswordManager\Security\Authentication\Plugin;
use Pcsg\GroupPasswordManager\Security\Authentication\SecurityClass;
use Pcsg\GroupPasswordManager\Security\Interfaces\IAuthPlugin;
use Pcsg\GroupPasswordManager\Security\KDF;
use Pcsg\GroupPasswordManager\Security\Keys\AuthKeyPair;
use Pcsg\GroupPasswordManager\Security\Keys\Key;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use QUI;
use Pcsg\GroupPasswordManager\Security\Authentication\Cache as AuthCache;

/**
 * Authentication Class for crypto data and crypto users
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Authentication
{
    const AUTH_MODE_TIME          = 1;
    const AUTH_MODE_SINGLE_ACTION = 2;

    /**
     * Runtime cache for Plugins
     *
     * @var array
     */
    protected static $plugins = array();

    /**
     * Runtime cache for SecurityClasses
     *
     * @var array
     */
    protected static $securityClasses = array();

    /**
     * Runtime cache for AuthKeyPairs
     *
     * @var array
     */
    protected static $authKeyPairs = array();

    /**
     * Runtime AuthKey cache
     *
     * @var Key[]
     */
    protected static $authKeys = array();

    /**
     * Flag:
     *
     * If true, save all derived keys from authentication plugins in session data
     *
     * @var bool
     */
    public static $sessionCache = false;

    /**
     * Return AuthKeyPair
     *
     * @param integer $id
     * @return AuthKeyPair
     */
    public static function getAuthKeyPair($id)
    {
        if (isset(self::$authKeyPairs[$id])) {
            return self::$authKeyPairs[$id];
        }

        self::$authKeyPairs[$id] = new AuthKeyPair($id);

        return self::$authKeyPairs[$id];
    }

    /**
     * Return list of all installed authentication plugins including:
     *
     * - id
     * - title
     * - description
     * - individual registration status for current session user
     *
     * @return array
     */
    public static function getAuthPluginList()
    {
        $list = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id',
                'title',
                'description'
            ),
            'from'   => Tables::authPlugins()
        ));

        $CryptoUser = CryptoActors::getCryptoUser();

        foreach ($result as $row) {
            $AuthPlugin = self::getAuthPlugin($row['id']);

            $row['registered'] = self::isRegistered(
                $CryptoUser,
                $AuthPlugin
            );

            $sync = count($CryptoUser->getNonFullyAccessiblePasswordIds($AuthPlugin)) > 0;

            if (!$sync) {
                $sync = count($CryptoUser->getNonFullyAccessibleGroupAndSecurityClassIds($AuthPlugin)) > 0;
            }

            $row['sync'] = $sync;

            $list[] = $row;
        }

        return $list;
    }

    /**
     * Checks if a user has a key pair for an authentication plugin
     *
     * @param CryptoUser $User
     * @param Plugin $Plugin
     * @return bool
     */
    public static function isRegistered($User, $Plugin)
    {
        $result = QUI::getDataBase()->fetch(array(
            'count' => 1,
            'from'  => Tables::keyPairsUser(),
            'where' => array(
                'userId'       => $User->getId(),
                'authPluginId' => $Plugin->getId()
            )
        ));

        if (current(current($result)) == 0) {
            return false;
        }

        return true;
    }

    /**
     * Get an authentication plugin by
     *
     * @param $id
     * @return Plugin
     * @throws QUI\Exception
     */
    public static function getAuthPlugin($id)
    {
        if (isset(self::$plugins[$id])) {
            return self::$plugins[$id];
        }

        self::$plugins[$id] = new Plugin($id);

        return self::$plugins[$id];
    }

    /**
     * Get all authentication plugins
     *
     * @return array
     */
    public static function getAuthPlugins()
    {
        $authPlugins = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => Tables::authPlugins(),
        ));

        foreach ($result as $row) {
            $authPlugins[] = self::getAuthPlugin($row['id']);
        }

        return $authPlugins;
    }

    /**
     * Return every auth plugin associated with a specific auth level
     *
     * @param integer $securityClassId - ID of security class
     * @return array
     * @throws QUI\Exception
     */
    public static function getAuthPluginsBySecurityClass($securityClassId)
    {
        $plugins = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'authPluginId'
            ),
            'from'   => Tables::securityClassesToAuthPlugins(),
            'where'  => array(
                'securityClassId' => (int)$securityClassId
            )
        ));

        if (empty($result)) {
            return $plugins;
        }

        $authPluginIds = array();

        foreach ($result as $row) {
            $authPluginIds[] = $row['authPluginId'];
        }

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => Tables::authPlugins(),
            'where'  => array(
                'id' => array(
                    'type'  => 'IN',
                    'value' => $authPluginIds
                )
            )
        ));

        foreach ($result as $row) {
            try {
                $plugins[] = new Plugin($row['id']);
            } catch (\Exception $Exception) {
                QUI\System\Log::addError(
                    'Could not load auth plugin: ' . $Exception->getMessage()
                );
            }
        }

        return $plugins;
    }

    /**
     * Checks if an authentication plugin is already registered
     *
     * @param string $path
     * @return bool
     */
    protected static function isAuthPluginRegistered($path)
    {
        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::authPlugins(),
            'where' => array(
                'path' => $path
            )
        ));

        if (empty($result)) {
            return false;
        }

        return true;
    }

    /**
     * Register an authentication plugin
     *
     * @param string $classPath - path to the main authentication plugin class
     * @param string $title - title of the authentication plugin
     * @param string $description (optional) - description of the authentication plugin
     * @throws QUI\Exception
     */
    public static function registerPlugin($classPath, $title, $description = null)
    {
        $class = '\\' . $classPath;

        $AuthClass = new $class();

        if (!($AuthClass instanceof IAuthPlugin)) {
            throw new QUI\Exception(
                'The plugin "' . $title . '" cannot be registered. The authentication class has'
                . ' to implement IAuthPlugin interface.'
            );
        }

        if (!(self::isAuthPluginRegistered($class))) {
            QUI::getDataBase()->insert(
                Tables::authPlugins(),
                array(
                    'title'       => $title,
                    'description' => is_null($description) ? '' : $description,
                    'path'        => $class
                )
            );
        } else {
            QUI::getDataBase()->update(
                Tables::authPlugins(),
                array(
                    'title'       => $title,
                    'description' => is_null($description) ? '' : $description,
                ),
                array(
                    'path' => $class
                )
            );
        }
    }

    /**
     * Loads all authentication plugins that are installed as quiqqer packages
     */
    public static function loadAuthPlugins()
    {
        QUI::getEvents()->fireEvent('pcsgGpmLoadAuthPlugins');
    }

    /**
     * Create new security class
     *
     * @param array $params
     * @return integer - security class id
     * @throws QUI\Exception
     */
    public static function createSecurityClass($params)
    {
        if (!QUI\Permissions\Permission::hasPermission(Permissions::SECURITY_CLASS_EDIT)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.create.no.permission'
            ));
        }

        if (!isset($params['title'])
            || empty($params['title'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.create.missing.title'
            ));
        }

        if (!isset($params['authPluginIds'])
            || empty($params['authPluginIds']
                     || !is_array($params['authPluginIds']))
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.create.missing.authplugins'
            ));
        }

        if (!isset($params['requiredFactors'])
            || empty($params['requiredFactors'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.create.missing.requiredFactors'
            ));
        }

        if ((int)$params['requiredFactors'] > count($params['authPluginIds'])) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.create.too.many.requiredFactors'
            ));
        }

        $authPlugins = array();

        foreach ($params['authPluginIds'] as $authPluginId) {
            try {
                $authPlugins[] = self::getAuthPlugin($authPluginId);
            } catch (\Exception $Exception) {
                QUI\System\Log::addError(
                    'createSecurityClass :: error on getAuthPlugin -> ' . $Exception->getMessage()
                );
            }
        }

        try {
            QUI::getDataBase()->insert(
                Tables::securityClasses(),
                array(
                    'title'           => $params['title'],
                    'description'     => $params['description'],
                    'requiredFactors' => (int)$params['requiredFactors']
                )
            );

            $securityClassId = QUI::getDataBase()->getPDO()->lastInsertId();

            /** @var Plugin $AuthPlugin */
            foreach ($authPlugins as $AuthPlugin) {
                QUI::getDataBase()->insert(
                    Tables::securityClassesToAuthPlugins(),
                    array(
                        'securityClassId' => $securityClassId,
                        'authPluginId'    => $AuthPlugin->getId()
                    )
                );
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Authentication :: createSecurityClass -> Error on inserting security class data into database: '
                . $Exception->getMessage()
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.create.error'
            ));
        }

        return $securityClassId;
    }

    /**
     * Return list of all security classes with name and description and associated authentication plugins
     *
     * @return array
     */
    public static function getSecurityClassesList()
    {
        $list   = array();
        $result = QUI::getDataBase()->fetch(array(
            'from' => Tables::securityClasses(),
        ));

        foreach ($result as $row) {
            $id = (int)$row['id'];

            $list[$id] = array(
                'id'              => $id,
                'title'           => $row['title'],
                'description'     => $row['description'],
                'authPlugins'     => array(),
                'requiredFactors' => $row['requiredFactors']
            );

            $authPlugins = self::getAuthPluginsBySecurityClass($id);

            /** @var Plugin $AuthPlugin */
            foreach ($authPlugins as $AuthPlugin) {
                $list[$id]['authPlugins'][] = array(
                    'id'          => $AuthPlugin->getId(),
                    'title'       => $AuthPlugin->getAttribute('title'),
                    'description' => $AuthPlugin->getAttribute('description')
                );
            }
        }

        uasort($list, function ($a, $b) {
            $authPluginsA = count($a['authPlugins']);
            $authPluginsB = count($b['authPlugins']);

            if ($authPluginsA === $authPluginsB) {
                return 0;
            }

            return $authPluginsA < $authPluginsB ? -1 : 1;
        });

        return $list;
    }

    /**
     * Get security class
     *
     * @param $id
     * @return SecurityClass
     * @throws QUI\Exception
     */
    public static function getSecurityClass($id)
    {
        if (isset(self::$securityClasses[$id])) {
            return self::$securityClasses[$id];
        }

        self::$securityClasses[$id] = new SecurityClass($id);

        return self::$securityClasses[$id];
    }

    /**
     * Save derived key from authenticated plugin to user session
     *
     * @param $authPluginId
     * @param $authKey
     */
    public static function saveAuthKey($authPluginId, $authKey)
    {
        $Session            = QUI::getSession();
        $currentAuthKeyData = json_decode($Session->get('quiqqer_pwm_authkeys'), true);

        if (empty($currentAuthKeyData)) {
            $currentAuthKeyData = array();
        }

        if (!isset($currentAuthKeyData['starttime'])) {
            $currentAuthKeyData['starttime'] = time();
        }

        $encryptedKey = SymmetricCrypto::encrypt(
            $authKey,
            self::getSessionEncryptionKey()
        );

        $currentAuthKeyData[$authPluginId] = base64_encode($encryptedKey);
        $Session->set('quiqqer_pwm_authkeys', json_encode($currentAuthKeyData));

        $Session->set(
            'quiqqer_pwm_authmode',
            self::$sessionCache ? self::AUTH_MODE_TIME : self::AUTH_MODE_SINGLE_ACTION
        );
    }

    /**
     * Check if an authentication key for an AuthPlugin exists in the session
     *
     * @param int $authPluginId
     * @return bool
     */
    public static function existsAuthKeyInSession($authPluginId)
    {
        $Session            = QUI::getSession();
        $currentAuthKeyData = json_decode($Session->get('quiqqer_pwm_authkeys'), true);
        return !empty($currentAuthKeyData[$authPluginId]);
    }

    /**
     * Retrieve derived key from session data
     *
     * @param $authPluginId
     * @param int $authPluginId - Auth Plugin ID
     * @return false|Key - false if no key set; key as string otherwise
     */
    public static function getAuthKey($authPluginId)
    {
        $Session            = QUI::getSession();
        $currentAuthKeyData = json_decode($Session->get('quiqqer_pwm_authkeys'), true);
        $authMode           = $Session->get('quiqqer_pwm_authmode');

        if (isset(self::$authKeys[$authPluginId])) {
            return self::$authKeys[$authPluginId];
        }

        if (empty($currentAuthKeyData)) {
            $currentAuthKeyData = array();
        }

        if (isset($currentAuthKeyData['starttime'])) {
            $start     = $currentAuthKeyData['starttime'];
            $timeAlive = time() - $start;
            $max       = QUI::getPackage('pcsg/grouppasswordmanager')->getConfig()->get(
                'settings',
                'auth_ttl'
            );

            if ($timeAlive > $max) {
                self::clearAuthInfoFromSession();
                return false;
            }
        }

        if (!isset($currentAuthKeyData[$authPluginId])) {
            return false;
        }

        try {
            $encryptedKey = base64_decode($currentAuthKeyData[$authPluginId]);

            $keyData = SymmetricCrypto::decrypt(
                $encryptedKey,
                self::getSessionEncryptionKey()
            );

            $Key = new Key($keyData);

            self::$authKeys[$authPluginId] = $Key;

            // delete from Session if auth data should not be safed
            if ($authMode === self::AUTH_MODE_SINGLE_ACTION) {
                unset($currentAuthKeyData[$authPluginId]);
                $Session->set('quiqqer_pwm_authkeys', json_encode($currentAuthKeyData));
            }

            return $Key;
        } catch (\Exception $Exception) {
            self::clearAuthInfoFromSession();
            return false;
        }
    }

    /**
     * Get a (new) encryption key for sensitive session data
     *
     * @return Key - Symmetric encryption key
     */
    protected static function getSessionEncryptionKey()
    {
        $cacheName = 'pcsg/gpm/authentication/session_key/' . QUI::getUserBySession()->getId();

        try {
            $keyValue = AuthCache::get($cacheName);
            return new Key($keyValue);
        } catch (\Exception $Exception) {
            // generate new key
        }

        $SessionKey = KDF::createKey(QUI::getSession()->getId());
        AuthCache::set($cacheName, $SessionKey->getValue());

        return $SessionKey;
    }

    /**
     * Deletes all authentication keys from session
     *
     * @return void
     */
    public static function clearAuthInfoFromSession()
    {
        QUI::getSession()->set('quiqqer_pwm_authkeys', false);
        AuthCache::clear('pcsg/gpm/authentication/session_key/' . QUI::getUserBySession()->getId());
    }

    /**
     * Get ID of default authentication plugin (QUIQQER Password auth)
     *
     * @return false|int - false if default plugin not installed; ID otherwise
     */
    public static function getDefaultAuthPluginId()
    {
        // get ID of basic quiqqer auth plugin
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => Tables::authPlugins(),
            'where'  => array(
                'path' => '\\' . AuthPlugin::class
            )
        ));

        if (empty($result)) {
            return false;
        }

        return (int)$result[0]['id'];
    }

    /**
     * Get the symmetric key that is used for encryption
     * between frontend and backend for the current session
     *
     * @return false|string - false if no key set, key otherwise
     */
    public static function getSessionCommunicationKey()
    {
        $data = QUI::getSession()->get('pcsg-gpm-comm-key');

        if (!$data) {
            return false;
        }

        $data = json_decode($data, true);

        $data['key'] = hex2bin($data['key']);
        $data['iv']  = hex2bin($data['iv']);

        return $data;
    }
}
