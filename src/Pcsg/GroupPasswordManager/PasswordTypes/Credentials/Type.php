<?php

namespace Pcsg\GroupPasswordManager\PasswordTypes\Credentials;

use Pcsg\GroupPasswordManager\PasswordTypes\TemplateUtils;
use QUI;
use Pcsg\GroupPasswordManager\PasswordTypes\IPasswordType;

/**
 * Type class for Website password input type
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
        $content = array_merge($content, self::getTemplateTranslations());
        return TemplateUtils::parseTemplate(dirname(__FILE__) . '/View.html', $content);
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
        $lgPrefix = 'passwordtypes.credentials.label.';

        return array(
            'labelTitle'    => $L->get($lg, $lgPrefix . 'title'),
            'labelUser'     => $L->get($lg, $lgPrefix . 'user'),
            'labelPassword' => $L->get($lg, $lgPrefix . 'password'),
            'labelNote'     => $L->get($lg, 'passwordtypes.label.note')
        );
    }
}
