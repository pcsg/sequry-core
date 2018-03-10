<?php

namespace Sequry\Core\Security\Keys;

use Sequry\Core\Security\HiddenString;

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
     * @param HiddenString $publicKeyValue - value of public key
     * @param HiddenString $privateKeyValue - value of private key
     */
    public function __construct(HiddenString $publicKeyValue, HiddenString $privateKeyValue)
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
