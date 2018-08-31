<?php

namespace Sequry\Core\PasswordTypes\Text\Layouts\Core;

use Sequry\Core\PasswordTypes\AbstractPasswordTypeLayout;
use QUI;

/**
 * Type class for Text password input type
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
            'textLabel'       => $L->get($lg, $lgPrefix.'text'),
            'textPlaceholder' => $L->get($lg, $lgPrefix.'textPlaceholder')
        ];
    }
}
