<?php

namespace Sequry\Core\Security\Keys;

use Sequry\Core\Security\HiddenString;
use Sequry\Core\Security\Utils;

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
     * @var HiddenString
     */
    protected $value = null;

    /**
     * Key constructor.
     *
     * @param HiddenString $keyValue - key value
     */
    public function __construct(HiddenString $keyValue)
    {
        $keyValue = Utils::stripModuleVersionString($keyValue->getString());
        $this->value = new HiddenString($keyValue);
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
