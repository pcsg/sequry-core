<?php

namespace Sequry\Core\PasswordTypes\Website;

use Sequry\Core\PasswordTypes\TemplateUtils;
use QUI;
use Sequry\Core\PasswordTypes\IPasswordType;

/**
 * Type class for Website password input type
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
    public static function getViewHtml($content = array())
    {
        if (isset($content['url'])
            && !empty($content['url'])
        ) {
            $url = $content['url'];

            if (mb_strpos($url, '//') !== false) {
                $url = '<a href="' . $url . '" target="_blank">' . $url . '</a>';
            }

            $content['url'] = $url;
        }

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
        $lgPrefix = 'passwordtypes.website.label.';

        return array(
            'labelTitle'    => $L->get($lg, $lgPrefix . 'title'),
            'labelUser'     => $L->get($lg, $lgPrefix . 'user'),
            'labelPassword' => $L->get($lg, $lgPrefix . 'password'),
            'labelUrl'      => $L->get($lg, $lgPrefix . 'url'),
            'labelNote'     => $L->get($lg, 'passwordtypes.label.note')
        );
    }
}
