<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

use Pcsg\GroupPasswordManager\Security\Keys\Key;

/**
 * This class provides a authentication plugin API for the pcsg/grouppasswordmanager module
 */
interface iAuthPlugin
{
    /**
     * Return internal name of auth plugin
     *
     * @return String
     */
    public static function getName();

    /**
     * Registers a user with this plugin
     *
     * @param mixed $information - authentication information given by the user
     * @param \QUI\Users\User $User (optional) - if omitted, use current session user
     */
    public static function register($information, $User = null);

    /**
     * Checks if the current user is successfully registered with this auth plugin
     *
     * @param \QUI\Users\User $User (optional) - if omitted, use current session user
     * @return bool
     */
    public static function isRegistered($User = null);

    /**
     * Get list of User IDs of users that are registered with this plugin
     *
     * @return array
     */
    public static function getRegisteredUserIds();

    /**
     * Get the derived key from the authentication information of a specific user
     *
     * @param \QUI\Users\User $User (optional) - if omitted, use current session user
     * @return Key
     */
    public static function getDerivedKey($User = null);

    /**
     * Authenticate a user with this plugin
     *
     * @param mixed $information - authentication information given by the user
     * @param \QUI\Users\User $User (optional) - if omitted, use current session user
     * @return true - if authenticated
     * @throws \QUI\Exception
     */
    public static function authenticate($information, $User = null);

    /**
     * Checks if a user is successfully authenticated for this runtime
     *
     * @param \QUI\Users\User $User (optional) - if omitted, use current session user
     * @return bool
     */
    public static function isAuthenticated($User = null);

    /**
     * Change authentication information
     *
     * @param mixed $old - current authentication information
     * @param mixed $new - new authentication information
     * @param \QUI\Users\User $User (optional) - if omitted, use current session user
     * @return bool - success
     */
    public static function changeAuthenticationInformation($old, $new, $User = null);

    /**
     * Registers the auth plugin with the main password manager module
     *
     * @return void
     */
    public static function registerPlugin();

    /**
     * Returns a QUI\Control object that collects registration information
     *
     * @return \QUI\Control
     */
    public static function getRegistrationControl();

    /**
     * Returns a QUI\Control object that collects authentification information
     *
     * @return \QUI\Control
     */
    public static function getAuthenticationControl();

    /**
     * Returns a QUI\Control object that allows changing of authentication information
     *
     * @return \QUI\Control
     */
    public static function getChangeAuthenticationControl();
}