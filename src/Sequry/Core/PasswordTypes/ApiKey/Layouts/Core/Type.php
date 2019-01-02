<?php

namespace Sequry\Core\PasswordTypes\ApiKey\Layouts\Core;

use Sequry\Core\PasswordTypes\AbstractPasswordTypeLayout;
use Sequry\Core\PasswordTypes\TemplateUtils;
use QUI;

/**
 * Type class for ApiKey password input type
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
            'urlLabel'                 => $L->get($lg, $lgPrefix . 'url'),
            'urlPlaceholder'           => $L->get($lg, $lgPrefix . 'urlPlaceholder'),
            'keyLabel'                 => $L->get($lg, $lgPrefix . 'key'),
            'keyPlaceholder'           => $L->get($lg, $lgPrefix . 'keyPlaceholder'),
            'generatePasswordBtnTitle' => $L->get($lg, 'sequry.utils.button.passwordGenerate'),
            'noteLabel'                => $L->get($lg, $lgPrefix . 'note'),
            'notePlaceholder'          => $L->get($lg, $lgPrefix . 'notePlaceholder')
        ];
    }
}
