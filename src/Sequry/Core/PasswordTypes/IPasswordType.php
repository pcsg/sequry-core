<?php

namespace Sequry\Core\PasswordTypes;

/**
 * Interface IPasswordType
 *
 * Interface for different password input types
 *
 * @package Sequry\Core\PasswordTypes
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

    /**
     * Get content that is copied by a copy action
     *
     * @param array $payload - password payload
     * @return string - copy content
     */
    public static function getCopyContent($payload);
}
