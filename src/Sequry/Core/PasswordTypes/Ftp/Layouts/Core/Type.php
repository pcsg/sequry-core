<?php

namespace Sequry\Core\PasswordTypes\Ftp\Layouts\Core;

use Sequry\Core\PasswordTypes\TemplateUtils;
use QUI;
use Sequry\Core\PasswordTypes\IPasswordType;

/**
 * Type class for Ftp password input type
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
            'hostLabel'           => $L->get($lg, $lgPrefix . 'host'),
            'hostPlaceholder'     => $L->get($lg, $lgPrefix . 'hostPlaceholder'),
            'userLabel'           => $L->get($lg, $lgPrefix . 'user'),
            'userPlaceholder'     => $L->get($lg, $lgPrefix . 'userPlaceholder'),
            'passwordLabel'       => $L->get($lg, $lgPrefix . 'password'),
            'passwordPlaceholder' => $L->get($lg, $lgPrefix . 'passwordPlaceholder'),
            'noteLabel'           => $L->get($lg, $lgPrefix . 'note'),
            'notePlaceholder'     => $L->get($lg, $lgPrefix . 'notePlaceholder')
        ];
    }
}
