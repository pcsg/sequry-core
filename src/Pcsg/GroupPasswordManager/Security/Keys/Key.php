<?php

namespace Pcsg\GroupPasswordManager\Security\Keys;

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
     * @param string $keyValue - key value
     */
    public function __construct($keyValue)
    {
        $this->value = $keyValue;
    }

    public function getValue()
    {
        return $this->value;
    }
}
