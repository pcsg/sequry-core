<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

/**
 * This class provides a MAC API for the pcsg/grouppasswordmanager module
 */
interface iMAC
{
    /**
     * Creates a MAC (Message Authentication Code)
     *
     * @param String $str - A String
     * @param String $key - Private key for MAC generation
     * @return String - The MAC hash
     */
    public static function create($str, $key);
}