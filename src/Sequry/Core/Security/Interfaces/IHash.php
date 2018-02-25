<?php

namespace Sequry\Core\Security\Interfaces;

use Sequry\Core\Security\HiddenString;

/**
 * This class provides a Hash API for the sequry/core module
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
