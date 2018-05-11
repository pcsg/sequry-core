<?php

namespace Sequry\Core\PasswordTypes\SecretKey;

use Sequry\Core\PasswordTypes\TemplateUtils;
use QUI;
use Sequry\Core\PasswordTypes\IPasswordType;

/**
 * Type class for SecretKey password input type
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
        $lgPrefix = 'passwordtypes.secretkey.label.';

        return array(
            'labelTitle'    => $L->get($lg, $lgPrefix . 'title'),
            'labelHost'     => $L->get($lg, $lgPrefix . 'host'),
            'labelUser'     => $L->get($lg, $lgPrefix . 'user'),
            'labelPassword' => $L->get($lg, $lgPrefix . 'password'),
            'labelKey'      => $L->get($lg, $lgPrefix . 'key'),
            'labelNote'     => $L->get($lg, 'passwordtypes.label.note')
        );
    }

    /**
     * Get content that is copied by a copy action
     *
     * @param array $payload - password payload
     * @return string - copy content
     */
    public static function getCopyContent($payload)
    {
        if (isset($payload['password'])) {
            return $payload['password'];
        }

        return '';
    }
}