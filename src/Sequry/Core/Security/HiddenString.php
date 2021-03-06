<?php
declare(strict_types=1);

namespace Sequry\Core\Security;

use ParagonIE\Halite\Util;

/**
 * Class HiddenString
 *
 * Things that should be protected:
 * - Passwords
 * - Plaintext (before encryption)
 * - Plaintext (after decryption)
 */
final class HiddenString
{
    /**
     * @var string
     */
    protected $internalStringValue = '';

    /**
     * Disallow the contents from being accessed via __toString()?
     *
     * @var bool
     */
    protected $disallowInline = false;

    /**
     * Disallow the contents from being accessed via __sleep()?
     *
     * @var bool
     */
    protected $disallowSerialization = false;

    /**
     * HiddenString constructor.
     * @param string $value
     * @param bool $disallowInline
     * @param bool $disallowSerialization
     */
    public function __construct(
        string $value,
        bool $disallowInline = false,
        bool $disallowSerialization = false
    ) {
        $this->internalStringValue = Util::safeStrcpy($value);
        $this->disallowInline = $disallowInline;
        $this->disallowSerialization = $disallowSerialization;
    }

    /**
     * Hide its internal state from var_dump()
     *
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'internalStringValue' =>
                '*',
            'attention' =>
                'If you need the value of a HiddenString, ' .
                'invoke getString() instead of dumping it.'
        ];
    }

    /**
     * Wipe it from memory after it's been used.
     */
    public function __destruct()
    {
        \Sodium\memzero($this->internalStringValue);
    }

    /**
     * Explicit invocation -- get the raw string value
     *
     * @return string
     */
    public function getString(): string
    {
        return Util::safeStrcpy($this->internalStringValue);
    }

    /**
     * Returns a copy of the string's internal value, which should be zeroed.
     * Optionally, it can return an empty string.
     *
     * @return string
     */
    public function __toString(): string
    {
        if (!$this->disallowInline) {
            return Util::safeStrcpy($this->internalStringValue);
        }
        return '';
    }

    /**
     * @return array
     */
    public function __sleep(): array
    {
        if (!$this->disallowSerialization) {
            return [
                'internalStringValue',
                'disallowInline',
                'disallowSerialization'
            ];
        }
        return [];
    }
}
