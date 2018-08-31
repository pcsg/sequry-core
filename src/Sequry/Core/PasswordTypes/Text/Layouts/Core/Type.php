<?php

namespace Sequry\Core\PasswordTypes\Text\Layouts\Core;

use Sequry\Core\PasswordTypes\TemplateUtils;
use QUI;
use Sequry\Core\PasswordTypes\IPasswordType;

/**
 * Type class for Text password input type
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
            'textLabel'           => $L->get($lg, $lgPrefix . 'text'),
            'textPlaceholder'     => $L->get($lg, $lgPrefix . 'textPlaceholder')
        ];
    }
}
