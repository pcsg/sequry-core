<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

/**
 * This class provides a Hash API for the pcsg/grouppasswordmanager module
 */
interface iHash
{
    /**
     * Creates a hash
     *
     * @param string $str - A String
     * @param string $salt (optional) - if omitted genereate random hash
     * @return string - The hash
     */
    public static function create($str, $salt = null);
}