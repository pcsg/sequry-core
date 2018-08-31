<?php

namespace Sequry\Core\PasswordTypes\SecretKey\Layouts\Core;

use Sequry\Core\PasswordTypes\AbstractPasswordTypeLayout;
use QUI;

/**
 * Type class for SecretKey password input type
 *
 * @package Sequry\Core\SecretKey
 */
class Type extends AbstractPasswordTypeLayout
{
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
            'hostLabel'           => $L->get($lg, $lgPrefix . 'host'),
            'hostPlaceholder'     => $L->get($lg, $lgPrefix . 'hostPlaceholder'),
            'userLabel'           => $L->get($lg, $lgPrefix . 'user'),
            'userPlaceholder'     => $L->get($lg, $lgPrefix . 'userPlaceholder'),
            'passwordLabel'       => $L->get($lg, $lgPrefix . 'password'),
            'passwordPlaceholder' => $L->get($lg, $lgPrefix . 'passwordPlaceholder'),
            'keyLabel'            => $L->get($lg, $lgPrefix . 'key'),
            'keyPlaceholder'      => $L->get($lg, $lgPrefix . 'keyPlaceholder'),
            'noteLabel'           => $L->get($lg, $lgPrefix . 'note'),
            'notePlaceholder'     => $L->get($lg, $lgPrefix . 'notePlaceholder')
        ];
    }
}
