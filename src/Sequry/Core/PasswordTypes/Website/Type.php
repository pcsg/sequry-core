<?php

namespace Sequry\Core\PasswordTypes\Website;

use Sequry\Core\PasswordTypes\AbstractPasswordType;
use Sequry\Core\PasswordTypes\TemplateUtils;
use QUI;
use Sequry\Core\Security\Utils;

/**
 * Type class for Website password input type
 *
 * @package Sequry\Core\PasswordTypes
 */
class Type extends AbstractPasswordType
{
    /**
     * Get view template
     *
     * @param array $content (optional) - the content that is parsed into the template
     * @return string - HTML template
     * @throws \Sequry\Core\Exception\Exception
     */
    public static function getViewHtml($content = [])
    {
        if (isset($content['url'])
            && !empty($content['url'])
        ) {
            $url = $content['url'];

            if (mb_strpos($url, '//') !== false) {
                $url = '<a href="'.$url.'" target="_blank">'.$url.'</a>';
            }

            $content['url'] = $url;
        }

        $content = Utils::sanitizeHtml($content);
        $content = array_merge($content, self::getTemplateTranslations());

        return TemplateUtils::parseTemplate(self::getDir().'/View.html', $content, true);
    }

    /**
     * Get password type icon (Fontawesome)
     *
     * @return string - Full fontawesome icon class name
     */
    public static function getIcon()
    {
        return 'fa fa-globe';
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
        $lgPrefix = 'passwordtypes.website.label.';

        return [
            'labelTitle'    => $L->get($lg, $lgPrefix.'title'),
            'labelUser'     => $L->get($lg, $lgPrefix.'user'),
            'labelPassword' => $L->get($lg, $lgPrefix.'password'),
            'labelUrl'      => $L->get($lg, $lgPrefix.'url'),
            'labelNote'     => $L->get($lg, 'passwordtypes.label.note')
        ];
    }
}
