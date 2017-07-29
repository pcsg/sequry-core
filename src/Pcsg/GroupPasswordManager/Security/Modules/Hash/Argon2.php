<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\Hash;

use Pcsg\GroupPasswordManager\Security\Interfaces\IHash;
use QUI;
use ParagonIE\Halite\Util;
use Pcsg\GroupPasswordManager\Security\HiddenString;

/**
 * This class provides a hashing API for the pcsg/grouppasswordmanager module
 */
class Argon2 implements IHash
{
    /**
     * Creates a hash
     *
     * @param HiddenString $str - A String
     * @param string $salt (optional) - if omitted genereate random hash
     * @return string - The hash
     *
     * @throws QUI\Exception
     */
    public static function create(HiddenString $str, $salt = null)
    {
        if (is_null($salt)) {
            $salt = \Sodium\randombytes_buf(\Sodium\CRYPTO_PWHASH_SALTBYTES);
        } else {
            // Argon2 needs a salt with fixed 16 bytes length
            if (Util::safeStrlen($salt) > \Sodium\CRYPTO_PWHASH_SALTBYTES) {
                $salt = Util::safeSubstr($salt, 0, \Sodium\CRYPTO_PWHASH_SALTBYTES);
            }
        }

        try {
            $hash = \Sodium\crypto_pwhash(
                \Sodium\CRYPTO_STREAM_KEYBYTES,
                $str->getString(),
                $salt,
                \Sodium\CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                \Sodium\CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
            );
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                self::class . ' :: Hash operation failed: ' . $Exception->getMessage()
            );
        }

        return $hash;
    }
}
