<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Password
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Security\Hash;
use Pcsg\GroupPasswordManager\Security\Interfaces\AuthPlugin;
use QUI;

/**
 * Authentication Class for crypto data and crypto users
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class CryptoAuth
{
    /**
     * Crypto authentication plugins
     *
     * @var array
     */
    protected static $_plugins = array();

    /**
     * Return every auth plugin associated with a specific auth level
     *
     * @param String $authLevel (optional) - Auth level determines the auth plugins used [default: "default"]
     * @return Array
     * @throws QUI\Exception
     */
    public static function getAuthPluginsByAuthLevel($authLevel = 'default')
    {
        $authPlugins = QUI::getPluginManager()
            ->get('pcsg/grouppasswordmanager')
            ->getConfig()
            ->get('auth_level', $authLevel);

        if (empty($authPlugins)) {
            throw new QUI\Exception(
                'CryptoAuth :: Cannot load auth plugins by auth level -> '
                . 'auth level "' . $authLevel . '" has no auth plugins '
                . 'configured. Please see config file of plugin.'
            );
        }

        $authPlugins = explode(",", $authPlugins);
        $pluginsLoaded = array();

        foreach ($authPlugins as $plugin) {
            $pluginsLoaded[$plugin] = self::getAuthPlugin($plugin);
        }
    }

    /**
     * Get an authentication plugin
     *
     * @param String $name
     * @param String $information (optional) - Information the plugin may use to generate a symmetric key
     * @return AuthPlugin
     * @throws QUI\Exception
     */
    public static function getAuthPlugin($name, $information = null)
    {
        if (empty(self::$_plugins)) {
            self::_loadAuthPlugins();
        }

        if (!isset(self::$_plugins[$name])) {
            throw new QUI\Exception(
                'Authentication plugin "' . $name . '" not found.'
            );
        }

        return new self::$_plugins[$name]($information);
    }

    /**
     * Register an authentication module
     *
     * @param String $authPluginClassName
     * @throws QUI\Exception
     */
    public static function registerPlugin($authPluginClassName)
    {
        $AuthPlugin = new $authPluginClassName();

        if (!($AuthPlugin instanceof AuthPlugin)) {
            throw new QUI\Exception(
                'Cannot register auth plugin. Wrong class type.'
            );
        }

        self::$_plugins[$AuthPlugin->getName()] = $authPluginClassName;
    }

    /**
     * Loads all authentication plugins
     */
    protected static function _loadAuthPlugins()
    {
        QUI::getEvents()->fireEvent('pcsgGpmLoadAuthPlugins');
    }
}