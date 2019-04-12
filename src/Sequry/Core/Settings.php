<?php

namespace Sequry\Core;

use QUI;

/**
 * Class Settings
 *
 * Settings helper class for Sequry core
 */
class Settings
{
    /**
     * Get sequry/core setting
     *
     * @param string $section
     * @param string $key
     * @return mixed
     */
    public static function getCoreSetting($key)
    {
        try {
            $Config = QUI::getPackage('sequry/core')->getConfig();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return null;
        }

        return $Config->get('settings', $key);
    }
}
