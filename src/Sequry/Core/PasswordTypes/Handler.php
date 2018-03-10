<?php

namespace Sequry\Core\PasswordTypes;

use QUI;
use QUI\Utils\System\File;
use QUI\Utils\Security\Orthos;

/**
 * Class Handler
 *
 * Handles different Password input types
 *
 * @package Sequry\Core\PasswordTypes
 */
class Handler
{
    /**
     * Type list runtime cache
     *
     * @var array
     */
    protected static $list = null;

    /**
     * Return a list of all available input types
     *
     * @return array
     */
    public static function getList()
    {
        if (!is_null(self::$list)) {
            return self::$list;
        }

        $types = array();
        $dir   = dirname(__FILE__);
        $files = File::readDir($dir);

        foreach ($files as $fileName) {
            $file = $dir . '/' . $fileName;

            // skip certain types, because they are no longer available
            // but have to be kept for legacy reasons
//            switch ($fileName) {
//                case 'Credentials': // removed: 2017-21-02
//                    continue 2;
//                    break;
//            }

            if (is_dir($file)) {
                $types[] = array(
                    'name'  => $fileName,
                    'title' => QUI::getLocale()->get(
                        'sequry/core',
                        'passwordtypes.' . $fileName . '.title'
                    )
                );
            }
        }

        usort($types, function ($a, $b) {
            $name1 = $a['name'];
            $name2 = $b['name'];

            if ($name1 == 'Website') {
                return -1;
            }

            if ($name1 == 'Credentials') {
                return 1;
            }

            if ($name2 == 'Website') {
                return 1;
            }

            if ($name2 == 'Credentials') {
                return -1;
            }

            if ($name1 === $name2) {
                return 0;
            }

            return $name1 < $name2 ? -1 : 1;
        });

        self::$list = $types;

        return $types;
    }

    /**
     * Check if a specific type exists in this setup
     *
     * @param string $type - type name
     * @return bool
     */
    public static function existsType($type)
    {
        $list = self::getList();

        foreach ($list as $entry) {
            if ($entry['name'] == $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return title of password input type
     *
     * @param $type
     * @return array|string
     */
    public static function getTypeTitle($type)
    {
        return QUI::getLocale()->get(
            'sequry/core',
            'passwordtypes.' . $type . '.title'
        );
    }

    /**
     * Return password type view template (parsed)
     *
     * @param string $type - password type
     * @param array $viewData - password view data
     * @return string - parsed view html template
     */
    public static function getViewHtml($type, $viewData)
    {
        // basic template
        $payload = array();

        if (isset($viewData['payload'])) {
            $payload = $viewData['payload'];
        }

        $TypeClass           = self::getPasswordTypeClass($type);
        $viewData['payload'] = $TypeClass->getViewHtml($payload);

        // labels
        $viewData['categoryPublicLabel'] = QUI::getLocale()->get(
            'sequry/core',
            'password.create.template.passwordCategory'
        );

        $viewData['categoryPrivateLabel'] = QUI::getLocale()->get(
            'sequry/core',
            'password.create.template.passwordCategoryPrivate'
        );

        // categories
        if (empty($viewData['categoryIds'])) {
            $viewData['categoryIds'] = '';
        } else {
            $viewData['categoryIds'] = implode(',', $viewData['categoryIds']);
        }

        if (empty($viewData['categoryIdsPrivate'])) {
            $viewData['categoryIdsPrivate'] = '';
        } else {
            $viewData['categoryIdsPrivate'] = implode(',', $viewData['categoryIdsPrivate']);
        }

        if ($viewData['favorite']) {
            $viewData['favoClass'] = 'fa fa-star';
        }

        // create and edit info
        $Users      = QUI::getUsers();
        $CreateUser = $Users->get($viewData['createUserId']);
        $EditUser   = $Users->get($viewData['editUserId']);

        $viewData['created']  = QUI::getLocale()->get(
            'sequry/core',
            'passwordtypes.handler.view.template.created',
            array(
                'date'     => date('Y-m-d H:i:s', $viewData['createDate']),
                'userName' => $CreateUser->getName(),
                'userId'   => $CreateUser->getId()
            )
        );
        $viewData['lastEdit'] = QUI::getLocale()->get(
            'sequry/core',
            'passwordtypes.handler.view.template.lastEdit',
            array(
                'date'     => date('Y-m-d H:i:s', $viewData['editDate']),
                'userName' => $EditUser->getName(),
                'userId'   => $EditUser->getId()
            )
        );

        return TemplateUtils::parseTemplate(dirname(__FILE__) . '/ViewHeader.html', $viewData);
    }

    /**
     * Return class
     *
     * @param string $type - password type
     * @return string - edit html template
     */
    public static function getEditTemplate($type)
    {
        $TypeClass = self::getPasswordTypeClass($type);

        $editHtml = $TypeClass->getEditHtml();

        // prepend fake input fields to disable Chrome and Firefox aggressive autofill
        $editHtml = '<input style="display: none;" type="text" name="username"/>
    <input style="display: none;" type="password" name="password"/>
    <input style="display: none;" type="password" name="key"/>' . $editHtml;

        return $editHtml;
    }

    /**
     * Get content that is copied by a copy action
     *
     * @param string $type - password type
     * @param array $payload - password payload
     * @return string - copy content
     */
    public static function getCopyContent($type, $payload)
    {
        $TypeClass = self::getPasswordTypeClass($type);
        return $TypeClass->getCopyContent($payload);
    }

    /**
     * Get class of password type
     *
     * @param $type
     * @return IPasswordType
     */
    protected static function getPasswordTypeClass($type)
    {
        $class = 'Sequry\\Core\\PasswordTypes\\' . $type . '\\Type';
        return new $class();
    }
}
