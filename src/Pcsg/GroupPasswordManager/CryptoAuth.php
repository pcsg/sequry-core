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