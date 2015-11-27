<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

/**
 * This class provides a authentication plugin API for the pcsg/grouppasswordmanager module
 */
interface AuthPlugin
{
    /**
     * @param String $information (optional) - Information the plugin may use to generate a symmetric key
     */
    public function __construct($information = null);

    /**
     * Return internal name of auth plugin
     *
     * @return String
     */
    public function getName();

    /**
     * Get unique key for this authentication type
     *
     * @return String
     */
    public function getKey();

    /**
     * Returns a QUI\Control object that collects information for generating
     * a unique key (i.e. input fields)
     *
     * @return \QUI\Control
     */
    public function getInputControl();

    /**
     * Set relevant information to generate unique symmetric key
     *
     * @param String $information
     * @return void
     */
    public function setInformation($information);

    /**
     * Registers the auth plugin with the CrpytoAuth Class
     *
     * @return void
     */
    public static function registerPlugin();
}