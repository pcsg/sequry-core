<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Password
 */

namespace Pcsg\GroupPasswordManager\Security\Handler;

use QUI;
use Pcsg\GroupPasswordManager\Constants\Tables;

/**
 * Class for for managing security classes
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class SecurityClasses
{
    /**
     * Crypto authentication plugins
     *
     * @var array
     */
    protected static $_plugins = array();

    /**
     * Return list of all security classes with name and description
     *
     * @return array
     */
    public static function getList()
    {
        $list = array();

        $result = QUI::getDataBase()->fetch(array(
            'from' => Tables::SECURITY_CLASSES,
        ));

        foreach ($result as $row) {
            $list[] = $row;
        }

        return $list;
    }
}