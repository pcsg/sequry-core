<?php

namespace Pcsg\GroupPasswordManager\Security\Modules\SecretSharing;

use Pcsg\GroupPasswordManager\Security\Interfaces\iSecretSharing;
use TQ;

/**
 * This class provides a secret splitting API for the pcsg/grouppasswordmanager module
 *
 * Splits a secret into multiple parts - Some parts are needed to revocer the secret
 *
 * @author PCSG (Patrick Müller)
 */
class Shamir implements iSecretSharing
{
    /**
     * Splits a secret into multiple parts
     *
     * @param string $secret
     * @param integer $parts - number of parts the secret is split into
     * @param integer $required - number of minimum required parts to recover the secret
     * @return array
     */
    public static function splitSecret($secret, $parts, $required)
    {
        return TQ\Shamir\Secret::share($secret, $parts, $required);
    }

    /**
     * Recover a secret from parts
     *
     * @param array $parts - the parts to recover the secret from
     * @return string - the secret
     */
    public static function recoverSecret($parts)
    {
        return TQ\Shamir\Secret::recover($parts);
    }
}