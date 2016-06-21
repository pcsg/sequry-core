<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Password
 */

namespace Pcsg\GroupPasswordManager\Security\Handler;

use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\CryptoUser;
use Pcsg\GroupPasswordManager\Security\Authentication\Plugin;
use Pcsg\GroupPasswordManager\Security\Interfaces\iAuthPlugin;
use QUI;

/**
 * Authentication Class for crypto data and crypto users
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Authentication
{
    /**
     * Loaded plugins
     *
     * @var array
     */
    protected static $plugins = array();

    /**
     * Return list of all installed authentication plugins including:
     *
     * - id
     * - title
     * - description
     * - individual registration status for current session user
     *
     */
    public static function getList()
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
            $AuthPlugin = self::getAuthPluginById($row['id']);

            $row['registered'] = self::isRegistered(
                CryptoActors::getCryptoUser(),
                $AuthPlugin
            );

            $list[] = $row;
        }

        $Grid = new \QUI\Utils\Grid();

        $result = $Grid->parseResult(
            $list,
            count($list)
        );

        return $result;
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
            'from'  => Tables::KEYPAIRS,
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
    public static function getAuthPluginById($id)
    {
        if (isset(self::$plugins[$id])) {
            return self::$plugins[$id];
        }

        self::$plugins[$id] = new Plugin($id);

        return self::$plugins[$id];
    }

//    /**
//     * Return every auth plugin authentication control associated with a specific auth level
//     *
//     * @param integer $securityClassId - ID of security class
//     * @return array
//     * @throws QUI\Exception
//     */
//    public static function getAuthenticationControls($securityClassId)
//    {
//        $controls    = array();
//        $authPlugins = self::getAuthPluginsBySecurityClass($securityClassId);
//
//        /** @var iAuthPlugin $AuthPlugin */
//        foreach ($authPlugins as $authPluginId => $AuthPlugin) {
//            $controls[$authPluginId] = $AuthPlugin->getAuthenticationControl();
//        }
//
//        return $controls;
//    }

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
}