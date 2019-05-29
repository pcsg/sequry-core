<?php

namespace Sequry\Core\PasswordTypes\Text;

use Sequry\Core\PasswordTypes\AbstractPasswordType;
use QUI;
use Sequry\Core\PasswordTypes\TemplateUtils;
use Sequry\Core\Security\Utils;

/**
 * Type class for Text password input type
 */
class Type extends AbstractPasswordType
{
    /**
     * Get password type icon (Fontawesome)
     *
     * @return string - Full fontawesome icon class name
     */
    public static function getIcon()
    {
        return 'fa fa-file-text-o';
    }

    /**
     * Get view template
     *
     * @param array $content (optional) - the content that is parsed into the template
     * @return string - HTML template
     * @throws \Sequry\Core\Exception\Exception
     */
    public static function getViewHtml($content = [])
    {
        $content = Utils::sanitizeHtml($content);
        $content = array_merge($content, self::getTemplateTranslations());

        return TemplateUtils::parseTemplate(self::getDir().'/View.html', $content, true);
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

        return [
            'labelText'  => $L->get($lg, $lgPrefix.'text'),
            'labelTitle' => $L->get($lg, $lgPrefix.'title')
        ];
    }
}
