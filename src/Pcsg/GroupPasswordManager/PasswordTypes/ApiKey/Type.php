<?php

namespace Pcsg\GroupPasswordManager\PasswordTypes\ApiKey;

use Pcsg\GroupPasswordManager\PasswordTypes\TemplateUtils;
use QUI;
use Pcsg\GroupPasswordManager\PasswordTypes\IPasswordType;

/**
 * Type class for SecretKey password input type
 *
 * @package Pcsg\GroupPasswordManager\PasswordTypes
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
        $lg       = 'pcsg/grouppasswordmanager';
        $lgPrefix = 'passwordtypes.apikey.label.';

        return array(
            'labelTitle' => $L->get($lg, $lgPrefix . 'title'),
            'labelUrl'   => $L->get($lg, $lgPrefix . 'url'),
            'labelKey'   => $L->get($lg, $lgPrefix . 'key'),
            'labelNote'  => $L->get($lg, 'passwordtypes.label.note')
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
        if (isset($payload['key'])) {
            return $payload['key'];
        }

        return '';
    }
}
