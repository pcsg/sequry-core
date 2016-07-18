<?php

namespace Pcsg\GroupPasswordManager\Security;

use Pcsg\GroupPasswordManager\Security\Interfaces\iSecretSharing;

/**
 * This class provides a key derivation API for the pcsg/grouppasswordmanager module
 */
class SecretSharing
{
    const SECRET_MODULE = 'Shamir'; // @todo in config auslagern

    /**
     * KDF Class Object for the configured hash module
     *
     * @var iSecretSharing
     */
    protected static $SecretModule = null;

    /**
     * @todo Secret Sharing nach Shamir einführen
     *
     * Splits a secret into multiple parts
     *
     * @param string $secret
     * @param integer $parts - number of parts the secret is split into
     * @param integer $required - number of parts that are required to recover the secret
     * @return array
     */
    public static function splitSecret($secret, $parts, $required)
    {
        return self::getSecretModule()->splitSecret($secret, $parts, $required);
    }

    /**
     * Recover a secret from parts
     *
     * @param array $parts - the parts to recover the secret from
     * @return string - the secret
     */
    public static function recoverSecret($parts)
    {
        return self::getSecretModule()->recoverSecret($parts);
    }

    /**
     * @return iSecretSharing
     */
    protected static function getSecretModule()
    {
        if (!is_null(self::$SecretModule)) {
            return self::$SecretModule;
        }

        $moduleClass = '\Pcsg\GroupPasswordManager\Security\Modules\SecretSharing\\';
        $moduleClass .= self::SECRET_MODULE;

        if (!class_exists($moduleClass)) {
            // @todo throw exception
            return false;
        }

        self::$SecretModule = new $moduleClass();

        return self::$SecretModule;
    }
}