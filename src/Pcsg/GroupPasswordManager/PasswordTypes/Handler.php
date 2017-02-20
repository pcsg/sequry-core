<?php

namespace Pcsg\GroupPasswordManager\PasswordTypes;

use QUI;
use QUI\Utils\System\File;

/**
 * Class Handler
 *
 * Handles different Password input types
 *
 * @package Pcsg\GroupPasswordManager\PasswordTypes
 */
class Handler
{
    /**
     * Return a list of all available input types
     *
     * @return array
     */
    public static function getList()
    {
        $types = array();
        $dir   = dirname(__FILE__);
        $files = File::readDir($dir);

        foreach ($files as $fileName) {
            $file = $dir . '/' . $fileName;

            if (is_dir($file)) {
                $types[] = array(
                    'name'  => $fileName,
                    'title' => QUI::getLocale()->get(
                        'pcsg/grouppasswordmanager',
                        'passwordtypes.' . $fileName . '.title'
                    )
                );
            }
        }

        return $types;
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
            'pcsg/grouppasswordmanager',
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
            'pcsg/grouppasswordmanager',
            'password.create.template.passwordCategory'
        );

        $viewData['categoryPrivateLabel'] = QUI::getLocale()->get(
            'pcsg/grouppasswordmanager',
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
            'pcsg/grouppasswordmanager',
            'passwordtypes.handler.view.template.created',
            array(
                'date'     => date('Y-m-d H:i:s', $viewData['createDate']),
                'userName' => $CreateUser->getUsername(),
                'userId'   => $CreateUser->getId()
            )
        );
        $viewData['lastEdit'] = QUI::getLocale()->get(
            'pcsg/grouppasswordmanager',
            'passwordtypes.handler.view.template.lastEdit',
            array(
                'date'     => date('Y-m-d H:i:s', $viewData['editDate']),
                'userName' => $EditUser->getUsername(),
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
        return $TypeClass->getEditHtml();
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
        $class = 'Pcsg\\GroupPasswordManager\\PasswordTypes\\' . $type . '\\Type';
        return new $class();
    }
}