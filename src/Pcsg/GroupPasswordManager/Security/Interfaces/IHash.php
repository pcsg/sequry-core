<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

use Pcsg\GroupPasswordManager\Security\HiddenString;

/**
 * This class provides a Hash API for the pcsg/grouppasswordmanager module
 */
interface IHash
{
    /**
     * Creates a hash
     *
     * @param HiddenString $str - A String
     * @param string $salt (optional) - if omitted genereate random hash
     * @return string - The hash
     */
    public static function create(HiddenString $str, $salt = null);
}
