<?php

namespace Sequry\Core\PasswordTypes\ApiKey;

use Sequry\Core\PasswordTypes\AbstractPasswordType;
use Sequry\Core\PasswordTypes\TemplateUtils;
use QUI;

/**
 * Type class for SecretKey password input type
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
    public static function getViewHtml($content = array())
    {
        if (isset($content['url'])
            && !empty($content['url'])
        ) {
            $url = $content['url'];

            preg_match('#https?:\/\/#i', $url, $matches);

            if (empty($matches)) {
                $url = 'http://' . $url;
            }

            $content['url'] = $url;
        } else {
            $content['url'] = '#';
        }

        $content = array_merge($content, self::getTemplateTranslations());

        return TemplateUtils::parseTemplate(dirname(__FILE__) . '/View.html', $content, true);
    }

    /**
     * Get password type icon (Fontawesome)
     *
     * @return string - Full fontawesome icon class name
     */
    public static function getIcon()
    {
        return 'fa fa-plug';
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
        $lgPrefix = 'passwordtypes.apikey.label.';

        return array(
            'labelTitle' => $L->get($lg, $lgPrefix . 'title'),
            'labelUrl'   => $L->get($lg, $lgPrefix . 'url'),
            'labelKey'   => $L->get($lg, $lgPrefix . 'key'),
            'labelNote'  => $L->get($lg, 'passwordtypes.label.note')
        );
    }
}
