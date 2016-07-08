<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

use Pcsg\GroupPasswordManager\Security\Keys\Key;

/**
 * This class provides a authentication plugin API for the pcsg/grouppasswordmanager module
 */
interface iAuthPlugin
{
    /**
     * @param \QUI\Users\User $User (optional) - The User this plugin should authenticate; if ommitted User = session user
     */
    public function __construct($User = null);

    /**
     * Return internal name of auth plugin
     *
     * @return String
     */
    public function getName();

    /**
     * Registers the current user and creates a new keypair
     *
     * @param \QUI\Users\User $User (optional) - if omitted, use current session user
     * @param mixed $information (optional) - authentication information given by the user
     */
    public static function register($User = null, $information = null);

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
     * Get the derived key from the authentication information
     *
     * @return Key
     */
    public function getDerivedKey();

    /**
     * Authenticate the current user
     *
     * @param mixed $information - authentication information given by the user
     * @return true - if authenticated
     * @throws \QUI\Exception
     */
    public function authenticate($information = null);

    /**
     * Checks if the current user is successfully authenticated for this runtime
     *
     * @return bool
     */
    public function isAuthenticated();

    /**
     * Change authentication information
     *
     * @param mixed $old - current authentication information
     * @param mixed $new - new authentication information
     * @return bool - success
     */
    public function changeAuthenticationInformation($old, $new);

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
    public function getRegistrationControl();

    /**
     * Returns a QUI\Control object that collects authentification information
     *
     * @return \QUI\Control
     */
    public function getAuthenticationControl();

    /**
     * Returns a QUI\Control object that allows changing of authentication information
     *
     * @return \QUI\Control
     */
    public function getChangeAuthenticationControl();
}