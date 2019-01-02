<?php

namespace Sequry\Core\Security\Modules\Hash;

use Sequry\Core\Security\Interfaces\IHash;
use QUI;
use ParagonIE\ConstantTime\Binary;
use Sequry\Core\Security\HiddenString;

/**
 * This class provides a hashing API for the sequry/core module
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
            $salt = \Sodium\randombytes_buf(\SODIUM_CRYPTO_PWHASH_SALTBYTES);
        } else {
            // Argon2 needs a salt with fixed 16 bytes length
            if (Binary::safeStrlen($salt) > \SODIUM_CRYPTO_PWHASH_SALTBYTES) {
                $salt = Binary::safeSubstr($salt, 0, \SODIUM_CRYPTO_PWHASH_SALTBYTES);
            }
        }

        try {
            $hash = \Sodium\crypto_pwhash(
                \SODIUM_CRYPTO_STREAM_KEYBYTES,
                $str->getString(),
                $salt,
                \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
            );
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                self::class . ' :: Hash operation failed: ' . $Exception->getMessage()
            );
        }

        return $hash;
    }
}
