<?php

namespace Sequry\Core\Security\Modules\KDF;

use Sequry\Core\Security\Interfaces\IKDF;
use ParagonIE\Halite\HiddenString as ParagonieHiddenString;
use Sequry\Core\Security\Keys\Key;
use QUI;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\ConstantTime\Binary;
use Sequry\Core\Security\HiddenString;

/**
 * This class provides a KDF (key derivation function) API for the sequry/core module
 */
class Halite3 implements IKDF
{
    /**
     * Derives a key from a string (and a salt)
     *
     * @param HiddenString $str - A String
     * @param string $salt (optional) - if ommitted, generate random salt
     * @return Key
     * @throws QUI\Exception
     */
    public static function createKey(HiddenString $str, $salt = null)
    {
        if (is_null($salt)) {
            $salt = \Sodium\randombytes_buf(\Sodium\CRYPTO_PWHASH_SALTBYTES);
        } else {
            // Argon2 needs a salt with fixed 16 bytes length
            if (Binary::safeStrlen($salt) > \Sodium\CRYPTO_PWHASH_SALTBYTES) {
                $salt = Binary::safeSubstr($salt, 0, \Sodium\CRYPTO_PWHASH_SALTBYTES);
            }
        }

        try {
            $HiddenString = new ParagonieHiddenString($str->getString());
            $DerivedKey = KeyFactory::deriveEncryptionKey($HiddenString, $salt);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                self::class . ' :: key derivation operation failed: ' . $Exception->getMessage()
            );
        }

        return new Key(new HiddenString($DerivedKey->getRawKeyMaterial()));
    }
}
