<?php

namespace Sequry\Core\PasswordTypes\Text;

use Sequry\Core\PasswordTypes\TemplateUtils;
use QUI;
use Sequry\Core\PasswordTypes\IPasswordType;

/**
 * Type class for Text password input type
 */
class Type implements IPasswordType
{
    /**
     * Get view template
     *
     * @param array $content (optional) - the content that is parsed into the template
     * @return string - HTML template
     */
    public static function getViewHtml($content = array())
    {
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
        $lg       = 'sequry/core';
        $lgPrefix = 'passwordtypes.text.label.';

        return array(
            'labelText'  => $L->get($lg, $lgPrefix . 'text'),
            'labelTitle' => $L->get($lg, $lgPrefix . 'title')
        );
    }
}
