<?php

namespace Sequry\Core\PasswordTypes\Website\Layouts\Core;

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
    public static function getViewHtml($content = [])
    {
        //todo
        /*if (isset($content['url'])
            && !empty($content['url'])
        ) {
            $url = $content['url'];

            if (mb_strpos($url, '//') !== false) {
                $url = '<a href="' . $url . '" target="_blank">' . $url . '</a>';
            }

            $content['url'] = $url;
        }

        $content = array_merge($content, self::getTemplateTranslations());

        return TemplateUtils::parseTemplate(dirname(__FILE__) . '/View.html', $content, true);*/
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
            'userLabel'           => $L->get($lg, $lgPrefix . 'user'),
            'userPlaceholder'     => $L->get($lg, $lgPrefix . 'userPlaceholder'),
            'passwordLabel'       => $L->get($lg, $lgPrefix . 'password'),
            'passwordPlaceholder' => $L->get($lg, $lgPrefix . 'passwordPlaceholder'),
            'urlLabel'            => $L->get($lg, $lgPrefix . 'url'),
            'urlPlaceholder'      => $L->get($lg, $lgPrefix . 'urlPlaceholder'),
            'noteLabel'           => $L->get($lg, $lgPrefix . 'note'),
            'notePlaceholder'     => $L->get($lg, $lgPrefix . 'notePlaceholder')
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
