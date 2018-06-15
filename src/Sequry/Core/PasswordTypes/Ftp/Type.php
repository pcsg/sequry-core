<?php

namespace Sequry\Core\PasswordTypes\Ftp;

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
    public static function getViewHtml($content = array())
    {
        if (isset($content['host'])
            && !empty($content['host'])
        ) {
            $host = $content['host'];

            if (mb_strpos($host, 'ftp://') === 0) {
                $url = $host;
            } else {
                $url = 'ftp://' . $host;
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
     * @param bool $frontned - parse template for frontend (true) or backend (false)
     * @param string $layout - name of the template
     * @return string - HTML template
     */
    public static function getEditHtml($frontned = false, $layout = 'core')
    {
        $content = self::getTemplateTranslations();

        if ($frontned) {
            return TemplateUtils::parseTemplate(dirname(__FILE__) . '/layouts/' . $layout . '/Edit.html', $content);
        }

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
        $lgPrefix = 'passwordtypes.ftp.label.';

        return array(
            'labelTitle'    => $L->get($lg, $lgPrefix . 'title'),
            'labelHost'     => $L->get($lg, $lgPrefix . 'host'),
            'labelUser'     => $L->get($lg, $lgPrefix . 'user'),
            'labelPassword' => $L->get($lg, $lgPrefix . 'password'),
            'labelUrl'      => $L->get($lg, $lgPrefix . 'url'),
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
