<?php

namespace Pcsg\GroupPasswordManager\PasswordTypes;

/**
 * Interface IPasswordType
 *
 * Interface for different password input types
 *
 * @package Pcsg\GroupPasswordManager\PasswordTypes
 */
interface IPasswordType
{
    /**
     * Get view template (just HTML, no data inserted)
     *
     * @param array $content (optional) - the content that is parsed into the template
     * @return string - HTML template
     */
    public static function getViewHtml($content = array());

    /**
     * Get edit template (just HTML)
     *
     * @return string - HTML template
     */
    public static function getEditHtml();
}
