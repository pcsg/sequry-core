<?php

namespace Sequry\Core\Security\Modules\Hash;

use Sequry\Core\Security\Interfaces\IHash;
use QUI;
use ParagonIE\ConstantTime\Binary;
use Sequry\Core\Security\HiddenString;
use function Sodium\crypto_pwhash;
use function Sodium\randombytes_buf;

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
            $salt = randombytes_buf(\SODIUM_CRYPTO_PWHASH_SALTBYTES);
        } else {
            // Argon2 needs a salt with fixed 16 bytes length
            if (Binary::safeStrlen($salt) > \SODIUM_CRYPTO_PWHASH_SALTBYTES) {
                $salt = Binary::safeSubstr($salt, 0, \SODIUM_CRYPTO_PWHASH_SALTBYTES);
            }
        }

        try {
            $hash = crypto_pwhash(
                \SODIUM_CRYPTO_STREAM_KEYBYTES,
                $str->getString(),
                $salt,
                \SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE,
                \SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_OPSLIMIT_SENSITIVE,
                \SODIUM_CRYPTO_PWHASH_ALG_ARGON2I13
            );
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                self::class . ' :: Hash operation failed: ' . $Exception->getMessage()
            );
        }

        return $hash;
    }
}
