<?php

namespace Sequry\Core\PasswordTypes\ApiKey\Layouts\Core;

use Sequry\Core\PasswordTypes\TemplateUtils;
use QUI;
use Sequry\Core\PasswordTypes\IPasswordType;

/**
 * Type class for ApiKey password input type
 *
 * @package Sequry\Core\PasswordTypes
 */
class Type implements IPasswordType
{
    /**
     * Get view template
     *
     * @param array $content (optional) - the content that is parsed into the template
     * @return string - HTML template
     */
    public static function getViewHtml($content = [])
    {
        // $content is no needed for frontend at the moment
        $content = array_merge($content, self::getTemplateTranslations());

        return TemplateUtils::parseTemplate(dirname(__FILE__) . '/View.html', $content, true);
    }

    /**
     * Get edit template (just HTML)
     *
     * @return string - HTML template
     */
    public static function getEditHtml()
    {
        $content = self::getTemplateTranslations();

        return TemplateUtils::parseTemplate(dirname(__FILE__) . '/Edit.html', $content);
    }

    /**
     * Return template translations
     *
     * @return array
     */
    protected static function getTemplateTranslations()
    {
        $L        = QUI::getLocale();
        $lg       = 'sequry/template';
        $lgPrefix = 'sequry.panel.template.';

        return [
            'urlLabel'        => $L->get($lg, $lgPrefix . 'url'),
            'urlPlaceholder'  => $L->get($lg, $lgPrefix . 'urlPlaceholder'),
            'keyLabel'        => $L->get($lg, $lgPrefix . 'key'),
            'keyPlaceholder'  => $L->get($lg, $lgPrefix . 'keyPlaceholder'),
            'noteLabel'       => $L->get($lg, $lgPrefix . 'note'),
            'notePlaceholder' => $L->get($lg, $lgPrefix . 'notePlaceholder')
        ];
    }

    /**
     * Get content that is copied by a copy action
     *
     * @param array $payload - password payload
     * @return string - copy content
     */
    public static function getCopyContent($payload)
    {
        /*if (isset($payload['password'])) {
            return $payload['password'];
        }

        return '';*/
    }
}