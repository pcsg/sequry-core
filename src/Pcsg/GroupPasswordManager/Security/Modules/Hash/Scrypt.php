<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\Hash;

use Pcsg\GroupPasswordManager\Security\Interfaces\IHash;
use QUI;
use Pcsg\GroupPasswordManager\Security\Classes\Scrypt as ScryptClass;

/**
 * This class provides a hashing API for the pcsg/grouppasswordmanager module
 */
class Scrypt implements IHash
{
    /**
     * Creates a hash
     *
     * @param string $str - A String
     * @param string $salt (optional) - if omitted genereate random hash
     * @return string - The hash
     *
     * @throws QUI\Exception
     */
    public static function create($str, $salt = null)
    {
        try {
            $hash = ScryptClass::createHash($str, $salt);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'Hash :: Hash operation failed: ' . $Exception->getMessage()
            );
        }
        
        return $hash;
    }
}
