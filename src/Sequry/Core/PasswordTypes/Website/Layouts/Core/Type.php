<?php

namespace Sequry\Core\PasswordTypes\Website\Layouts\Core;

use Sequry\Core\PasswordTypes\AbstractPasswordTypeLayout;
use QUI;

/**
 * Type class for Website password input type
 *
 * @package Sequry\Core\PasswordTypes
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
            'userLabel'                => $L->get($lg, $lgPrefix . 'user'),
            'userPlaceholder'          => $L->get($lg, $lgPrefix . 'userPlaceholder'),
            'passwordLabel'            => $L->get($lg, $lgPrefix . 'password'),
            'passwordPlaceholder'      => $L->get($lg, $lgPrefix . 'passwordPlaceholder'),
            'generatePasswordBtnTitle' => $L->get($lg, 'sequry.utils.button.passwordGenerate'),
            'urlLabel'                 => $L->get($lg, $lgPrefix . 'url'),
            'urlPlaceholder'           => $L->get($lg, $lgPrefix . 'urlPlaceholder'),
            'noteLabel'                => $L->get($lg, $lgPrefix . 'note'),
            'notePlaceholder'          => $L->get($lg, $lgPrefix . 'notePlaceholder')
        ];
    }
}
