<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Password
 */

namespace Pcsg\GroupPasswordManager\Security\Handler;

use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Actors\CryptoUser;
use Pcsg\GroupPasswordManager\Security\Authentication\Plugin;
use Pcsg\GroupPasswordManager\Security\Authentication\SecurityClass;
use Pcsg\GroupPasswordManager\Security\Interfaces\iAuthPlugin;
use QUI;
use Symfony\Component\Console\Helper\Table;

/**
 * Authentication Class for crypto data and crypto users
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Authentication
{
    /**
     * Loaded plugin objects
     *
     * @var array
     */
    protected static $plugins = array();

    /**
     * Loaded security class objects
     *
     * @var array
     */
    protected static $securityClasses = array();

    /**
     * Return list of all installed authentication plugins including:
     *
     * - id
     * - title
     * - description
     * - individual registration status for current session user
     *
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
            'from'   => Tables::AUTH_PLUGINS
        ));

        foreach ($result as $row) {
            $AuthPlugin = self::getAuthPlugin($row['id']);

            $row['registered'] = self::isRegistered(
                CryptoActors::getCryptoUser(),
                $AuthPlugin
            );

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
            'from'  => Tables::KEYPAIRS_USER,
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
            'from'   => Tables::SECURITY_TO_AUTH,
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
            'from'   => Tables::AUTH_PLUGINS,
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
            'from'  => Tables::AUTH_PLUGINS,
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

        if (!($AuthClass instanceof iAuthPlugin)) {
            throw new QUI\Exception(
                'The plugin "' . $title . '" cannot be registered. The authentication class has'
                . ' to implement iAuthPlugin interface.'
            );
        }

        if (!(self::isAuthPluginRegistered($class))) {
            QUI::getDataBase()->insert(
                Tables::AUTH_PLUGINS,
                array(
                    'title'       => $title,
                    'description' => is_null($description) ? '' : $description,
                    'path'        => $class
                )
            );
        } else {
            QUI::getDataBase()->update(
                Tables::AUTH_PLUGINS,
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
        if (!isset($params['title'])
            || empty($params['title'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.create.missing.title'
            ));
        }

        if (!isset($params['authPluginIds'])
            || empty($params['authPluginIds'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.securityclass.create.missing.authplugins'
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

        QUI::getDataBase()->insert(
            Tables::SECURITY_CLASSES,
            array(
                'title'       => $params['title'],
                'description' => $params['description']
            )
        );

        $securityClassId = QUI::getDataBase()->getPDO()->lastInsertId();

        /** @var Plugin $AuthPlugin */
        foreach ($authPlugins as $AuthPlugin) {
            QUI::getDataBase()->insert(
                Tables::SECURITY_TO_AUTH,
                array(
                    'securityClassId' => $securityClassId,
                    'authPluginId'    => $AuthPlugin->getId()
                )
            );
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
            'from' => Tables::SECURITY_CLASSES,
        ));

        foreach ($result as $row) {
            $id = (int)$row['id'];

            $list[$id] = array(
                'title'       => $row['title'],
                'description' => $row['description'],
                'authPlugins' => array()
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
}