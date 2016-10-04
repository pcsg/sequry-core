<?php

namespace Pcsg\GroupPasswordManager\PasswordTypes;

use QUI;

/**
 * Class TemplateUtils
 *
 * Utility methods for template managin
 *
 * @package Pcsg\GroupPasswordManager\PasswordTypes
 */
class TemplateUtils
{
    /**
     * Parses content into a template by replacing the content with the corresponding placeholders
     *
     * @param string $templateFile - path to template file
     * @param array $templateContent - placeholder replacement values
     * @return string - template HTML with replaced values
     *
     * @throws QUI\Exception
     */
    public static function parseTemplate($templateFile, $templateContent)
    {
        if (!file_exists($templateFile)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwordtypes.templateutils.template.file.not.found'
            ), 404);
        }

        $templateHtml = file_get_contents($templateFile);

        foreach ($templateContent as $placeHolder => $value) {
            $templateHtml = str_replace('{{' . $placeHolder . '}}', $value, $templateHtml);
        }

        return $templateHtml;
    }
}