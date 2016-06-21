<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

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
     * @param mixed $information - authentication information given by the user
     */
    public function register($information = null);

    /**
     * Authenticate the current user
     *
     * @param mixed $information - authentication information given by the user
     * @return mixed
     */
    public function authenticate($information = null);

    /**
     * Checks if the current user is successfully authenticated for this runtime
     *
     * @return bool
     */
    public function isAuthenticated();

    /**
     * Returns a QUI\Control object that collects authentification information
     *
     * @return \QUI\Control
     */
    public function getAuthenticationControl();

    /**
     * Registers the auth plugin with the main password manager module
     *
     * @return void
     */
    public static function registerPlugin();
}