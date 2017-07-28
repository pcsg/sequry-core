<?php

namespace Pcsg\GroupPasswordManager\Security\Keys;

use Pcsg\GroupPasswordManager\Security\HiddenString;

/**
 * Key class
 *
 * Holds a cryptographic key
 */
class Key
{
    /**
     * The key value
     *
     * @var string
     */
    protected $value = null;

    /**
     * Key constructor.
     *
     * @param HiddenString $keyValue - key value
     */
    public function __construct(HiddenString $keyValue)
    {
        $this->value = $keyValue;
    }

    /**
     * Return Key value
     *
     * @return HiddenString
     */
    public function getValue()
    {
        return $this->value;
    }
}
