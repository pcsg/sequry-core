<?php

use Sequry\Core\Security\Handler\Passwords;
use Sequry\Core\Security\Handler\CryptoActors;

/**
 * Get list of all PasswordLinks for a specific password
 *
 * @param integer $passwordId - ID of password
 * @param array $linkData - settings for PasswordLink
 * @return bool - success
 *
 * @throws QUI\Exception
 */
QUI::$Ajax->registerFunction(
    'package_sequry_core_ajax_passwords_link_getPasswordData',
    function ($passwordId) {
        $Password = Passwords::get((int)$passwordId);

        if ($Password->hasPasswordAccess(CryptoActors::getCryptoUser())) {
            return array(
                'title'       => $Password->getAttribute('title'),
                'description' => $Password->getAttribute('description')
            );
        }

        return array();
    },
    array('passwordId'),
    'Permission::checkAdminUser'
);
