<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

use Pcsg\GroupPasswordManager\Actors\CryptoUser;
use Pcsg\GroupPasswordManager\Security\Keys\Key;
use Pcsg\GroupPasswordManager\Security\HiddenString;

/**
 * This class provides a authentication plugin API for the pcsg/grouppasswordmanager module
 */
interface IAuthPlugin
{
    /**
     * Return locale data for auth plugin name
     *
     * @return array
     */
    public static function getNameLocaleData();

    /**
     * Return locale data for auth plugin description
     *
     * @return array
     */
    public static function getDescriptionLocaleData();

    /**
     * Registers a user with this plugin
     *
     * @param HiddenString $information - authentication information given by the user
     * @param \QUI\Users\User $User (optional) - if omitted, use current session user
     */
    public static function register(HiddenString $information, $User = null);

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
     * @param HiddenString $information - authentication information given by the user
     * @param \QUI\Users\User $User (optional) - if omitted, use current session user
     * @return true - if authenticated
     * @throws \QUI\Exception
     */
    public static function authenticate(HiddenString $information, $User = null);

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
     * @param HiddenString $old - current authentication information
     * @param HiddenString $new - new authentication information
     * @param \QUI\Users\User $User (optional) - if omitted, use current session user
     * @return bool - success
     */
    public static function changeAuthenticationInformation(HiddenString $old, HiddenString $new, $User = null);

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

    /**
     * Delete a user from this plugin
     *
     * @param CryptoUser $CryptoUser
     * @return mixed
     */
    public static function deleteUser($CryptoUser);
}
