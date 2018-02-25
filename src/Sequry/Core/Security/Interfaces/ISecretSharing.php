<?php

namespace Sequry\Core\Security\Interfaces;

use Sequry\Core\Security\HiddenString;

/**
 * This class provides a secret sharing plugin API for the sequry/core module
 */
interface ISecretSharing
{
    /**
     * Splits a secret into multiple parts
     *
     * @param HiddenString $secret
     * @param integer $parts - number of parts the secret is split into
     * @param integer $required - number of minimum required parts to recover the secret
     * @return array - secret parts
     */
    public static function splitSecret(HiddenString $secret, $parts, $required);

    /**
     * Recover a secret from parts
     *
     * @param array $parts - the parts to recover the secret from
     * @return HiddenString - the secret
     */
    public static function recoverSecret($parts);
}
