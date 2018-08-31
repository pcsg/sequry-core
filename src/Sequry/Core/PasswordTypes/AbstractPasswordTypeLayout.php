<?php

namespace Sequry\Core\PasswordTypes;

use QUI;

abstract class AbstractPasswordTypeLayout implements PasswordTypeInterface
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
        $viewHtmlFile = self::getDir().'/View.html';

        if (!file_exists($viewHtmlFile)) {
            return self::getBaseTypeClass()::getViewHtml($content);
        }

        $content = static::getTemplateTranslations();
        return TemplateUtils::parseTemplate($viewHtmlFile, $content, true);
    }

    /**
     * Get edit template
     *
     * @return string - HTML template
     * @throws \Sequry\Core\Exception\Exception
     */
    public static function getEditHtml()
    {
        $editHtmlFile = self::getDir().'/Edit.html';

        if (!file_exists($editHtmlFile)) {
            return self::getBaseTypeClass()::getEditHtml();
        }

        $content = static::getTemplateTranslations();
        return TemplateUtils::parseTemplate($editHtmlFile, $content);
    }

    /**
     * Get password type icon (Fontawesome)
     *
     * @return string - Full fontawesome icon class name
     */
    public static function getIcon()
    {
        return self::getBaseTypeClass()::getIcon();
    }

    /**
     * Return template translations
     *
     * @return array
     */
    protected static function getTemplateTranslations()
    {
        return [];
    }

    /**
     * Get class of base passwort type of this layout
     *
     * @return PasswordTypeInterface
     */
    private static function getBaseTypeClass()
    {
        try {
            $baseType = dirname(__FILE__, 2);
            return Handler::getPasswordTypeClass($baseType);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Get directory of this password type
     *
     * @return string
     */
    private static function getDir()
    {
        try {
            $Reflection = new \ReflectionClass(static::class);
            return dirname($Reflection->getFileName()).'/';
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }
}
