<?php

namespace Pcsg\GroupPasswordManager\Security;

use Pcsg\GroupPasswordManager\Security\Interfaces\ISecretSharing;

/**
 * This class provides a key derivation API for the pcsg/grouppasswordmanager module
 */
class SecretSharing
{
    const SECRET_MODULE = 'Shamir'; // @todo in config auslagern

    /**
     * KDF Class Object for the configured hash module
     *
     * @var ISecretSharing
     */
    protected static $SecretModule = null;

    /**
     * Splits a secret into multiple parts
     *
     * @param HiddenString $secret
     * @param integer $parts - number of parts the secret is split into
     * @param integer $required - number of parts that are required to recover the secret
     * @return array
     */
    public static function splitSecret($secret, $parts, $required)
    {
        $parts = self::getSecretModule()->splitSecret($secret, $parts, $required);

        foreach ($parts as $k => $part) {
            $parts[$k] = $part . Utils::getCryptoModuleVersionString(self::SECRET_MODULE);
        }

        return $parts;
    }

    /**
     * Recover a secret from parts
     *
     * @param string[] $parts - the parts to recover the secret from
     * @return HiddenString - the secret
     */
    public static function recoverSecret($parts)
    {
        foreach ($parts as $k => $part) {
            $parts[$k] = Utils::stripModuleVersionString($part);
        }

        return self::getSecretModule()->recoverSecret($parts);
    }

    /**
     * @return ISecretSharing
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
