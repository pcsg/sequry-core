<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\KDF;

use Pcsg\GroupPasswordManager\Security\Interfaces\IKDF;
use Pcsg\GroupPasswordManager\Security\KDF;
use QUI;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Util;

/**
 * This class provides a KDF (key derivation function) API for the pcsg/grouppasswordmanager module
 */
class Halite3 implements IKDF
{
    /**
     * Derives a key from a string (and a salt)
     *
     * @param string $str - A String
     * @param string $salt (optional) - if ommitted, generate random salt
     * @return string - raw key material
     * @throws QUI\Exception
     */
    public static function createKey($str, $salt = null)
    {
        if (is_null($salt)) {
            $salt = \Sodium\randombytes_buf(KDF::SALT_LENGTH);
        } else {
            // Argon2 needs a salt with fixed 16 bytes length
            if (Util::safeStrlen($salt) > KDF::SALT_LENGTH) {
                $salt = Util::safeSubstr($salt, 0, KDF::SALT_LENGTH);
            }
        }

        try {
            $DerivedKey = KeyFactory::deriveEncryptionKey($str, $salt);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                self::class . ' :: key derivation operation failed: ' . $Exception->getMessage()
            );
        }

        return $DerivedKey->getRawKeyMaterial();
    }
}
