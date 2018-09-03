<?php

namespace Sequry\Core\PasswordTypes;

use QUI;

abstract class AbstractPasswordType implements PasswordTypeInterface
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
        $content = array_merge($content, static::getTemplateTranslations());
        return TemplateUtils::parseTemplate(self::getDir().'/View.html', $content, true);
    }

    /**
     * Get edit template (just HTML)
     *
     * @return string - HTML template
     * @throws \Sequry\Core\Exception\Exception
     */
    public static function getEditHtml()
    {
        $content = static::getTemplateTranslations();
        return TemplateUtils::parseTemplate(self::getDir().'/Edit.html', $content);
    }

    /**
     * Get password type icon (Fontawesome)
     *
     * @return string - Full fontawesome icon class name
     */
    abstract public static function getIcon();

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
     * Get directory of this password type
     *
     * @return string
     */
    protected static function getDir()
    {
        try {
            $Reflection = new \ReflectionClass(static::class);
            return dirname($Reflection->getFileName()).'/';
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }
}
