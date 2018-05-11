<?php

namespace Sequry\Core\PasswordTypes;

use QUI;
use QUI\Utils\Security\Orthos;

/**
 * Class TemplateUtils
 *
 * Utility methods for template managin
 *
 * @package Sequry\Core\PasswordTypes
 */
class TemplateUtils
{
    /**
     * Parses content into a template by replacing the content with the corresponding placeholders
     *
     * @param string $templateFile - path to template file
     * @param array $templateContent - placeholder replacement values
     * @param bool $nl2br (optional) - apply nl2br to placeholder values
     * @return string - template HTML with replaced values
     *
     * @throws QUI\Exception
     */
    public static function parseTemplate($templateFile, $templateContent, $nl2br = false)
    {
        if (!file_exists($templateFile)) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.passwordtypes.templateutils.template.file.not.found'
            ), 404);
        }

        $templateHtml = file_get_contents($templateFile);

        foreach ($templateContent as $placeHolder => $value) {
            if ($nl2br) {
                $value = nl2br($value);
            }

            $templateHtml = str_replace('{{' . $placeHolder . '}}', $value, $templateHtml);
        }

        return $templateHtml;
    }
}