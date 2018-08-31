<?php

namespace Sequry\Core\PasswordTypes\Text;

use Sequry\Core\PasswordTypes\AbstractPasswordType;
use QUI;

/**
 * Type class for Text password input type
 */
class Type extends AbstractPasswordType
{
    /**
     * Get password type icon (Fontawesome)
     *
     * @return string - Full fontawesome icon class name
     */
    public static function getIcon()
    {
        return 'fa fa-file-text-o';
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
        $lgPrefix = 'passwordtypes.text.label.';

        return array(
            'labelText'  => $L->get($lg, $lgPrefix . 'text'),
            'labelTitle' => $L->get($lg, $lgPrefix . 'title')
        );
    }
}
