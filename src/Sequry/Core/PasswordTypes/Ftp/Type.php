<?php

namespace Sequry\Core\PasswordTypes\Ftp;

use Sequry\Core\PasswordTypes\AbstractPasswordType;
use Sequry\Core\PasswordTypes\TemplateUtils;
use QUI;
use Sequry\Core\Security\Utils;

/**
 * Type class for Ftp password input type
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
        $content = Utils::sanitizeHtml($content);

        if (!empty($content['host'])) {
            $host = $content['host'];

            if (mb_strpos($host, 'ftp://') === 0) {
                $url = $host;
            } else {
                $url = 'ftp://'.$host;
            }

            $urlSanitized = $url;

            preg_match('#(https?:\/\/)(.*)#i', $url, $matches);

            if (!empty($matches[1]) && !empty($matches[2])) {
                $urlSanitized = $matches[1].implode("/", array_map("urlencode", explode("/", $matches[2])));
            }

            $content['url'] = '<a href="'.$urlSanitized.'" target="_blank">'.$url.'</a>';
        } else {
            $content['url'] = '#';
        }

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
        return 'fa fa-server';
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

        return [
            'labelTitle'    => $L->get($lg, $lgPrefix.'title'),
            'labelHost'     => $L->get($lg, $lgPrefix.'host'),
            'labelUser'     => $L->get($lg, $lgPrefix.'user'),
            'labelPassword' => $L->get($lg, $lgPrefix.'password'),
            'labelUrl'      => $L->get($lg, $lgPrefix.'url'),
            'labelNote'     => $L->get($lg, 'passwordtypes.label.note')
        ];
    }
}
