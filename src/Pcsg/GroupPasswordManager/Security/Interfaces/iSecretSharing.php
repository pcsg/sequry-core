<?php

namespace Pcsg\GroupPasswordManager\Security\Interfaces;

/**
 * This class provides a secret sharing plugin API for the pcsg/grouppasswordmanager module
 */
interface iSecretSharing
{
    /**
     * Splits a secret into multiple parts
     *
     * @param string $secret
     * @param integer $parts - number of parts the secret is split into
     * @param integer $required - number of minimum required parts to recover the secret
     * @return void
     */
    public static function splitSecret($secret, $parts, $required);

    /**
     * Recover a secret from parts
     *
     * @param array $parts - the parts to recover the secret from
     * @return string - the secret
     */
    public static function recoverSecret($parts);
}