<?php

namespace Sequry\Core\Security\Modules\SecretSharing;

use Sequry\Core\Security\Interfaces\ISecretSharing;
use TQ;
use Sequry\Core\Security\HiddenString;

/**
 * This class provides a secret splitting API for the sequry/core module
 *
 * Splits a secret into multiple parts - Some parts are needed to revocer the secret
 *
 * @author PCSG (Patrick Müller)
 */
class Shamir implements ISecretSharing
{
    /**
     * Splits a secret into multiple parts
     *
     * @param HiddenString $secret
     * @param integer $parts - number of parts the secret is split into
     * @param integer $required - number of minimum required parts to recover the secret
     * @return array
     */
    public static function splitSecret(HiddenString $secret, $parts, $required)
    {
        return TQ\Shamir\Secret::share($secret->getString(), $parts, $required);
    }

    /**
     * Recover a secret from parts
     *
     * @param array $parts - the parts to recover the secret from
     * @return HiddenString - the secret
     */
    public static function recoverSecret($parts)
    {
        return new HiddenString(TQ\Shamir\Secret::recover($parts));
    }
}
