<?php

namespace Pcsg\GroupPasswordManager\Security\Keys;

/**
 * KeyPair class
 *
 * Holds a public and a corresponding private key
 */
class KeyPair
{
    /**
     * @var Key
     */
    protected $PublicKey = null;

    /**
     * @var Key
     */
    protected $PrivateKey = null;

    /**
     * KeyPair constructor.
     *
     * @param string $publicKeyValue - value of public key
     * @param string $privateKeyValue - value of private key
     */
    public function __construct($publicKeyValue, $privateKeyValue)
    {
        $this->PublicKey  = new Key($publicKeyValue);
        $this->PrivateKey = new Key($privateKeyValue);
    }

    /**
     * Get the public key
     *
     * @return Key
     */
    public function getPublicKey()
    {
        return $this->PublicKey;
    }

    /**
     * Get the private key
     *
     * @return Key
     */
    public function getPrivateKey()
    {
        return $this->PrivateKey;
    }
}