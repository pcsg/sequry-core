<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Security\Handler\PasswordLinks
 */

namespace Pcsg\GroupPasswordManager\Security\Handler;

use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Security\HiddenString;
use Pcsg\GroupPasswordManager\Security\Keys\Key;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use QUI;
use Pcsg\GroupPasswordManager\PasswordLink;
use Pcsg\GroupPasswordManager\Security\Hash;
use Pcsg\GroupPasswordManager\Security\Random;
use Pcsg\GroupPasswordManager\Exception\Exception;

/**
 * Class for for managing PasswordLinks
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class PasswordLinks
{
    /**
     * Password objects
     *
     * @var array
     */
    protected static $passwords = array();

    /**
     * Create new PasswordLink
     *
     * @param int $dataId - Password ID
     * @param array $settings
     * @return PasswordLink
     *
     * @throws \Pcsg\GroupPasswordManager\Exception\Exception
     */
    public static function create($dataId, $settings = array())
    {
        // check if Password is eligible for linking
        $Password = Passwords::get($dataId);

        $hash = Hash::create(
            new HiddenString(Random::getRandomData())
        );

        $passwordKey = $Password->getPasswordKey()->getValue()->getString();
        $password    = false;

        // additionally encrypt password data with an access password
        if (!empty($settings['password'])) {
            $passwordKey = SymmetricCrypto::encrypt(
                new HiddenString($passwordKey),
                new Key(new HiddenString($settings['password']))
            );

            $password = true;
        }

        $dataAccess = array(
            'password' => $password,
            'hash'     => \Sodium\bin2hex($hash),
            'dataKey'  => \Sodium\bin2hex($passwordKey),
            'calls'    => 0,
            'maxCalls' => false
        );

        if (!empty($settings['maxCalls'])) {
            $dataAccess['maxCalls'] = (int)$settings['maxCalls'];
        }

        // determine how long the link is valid
        if (empty($settings['validDate'])) {
            $validUntil = null;
        } else {
            $validUntil = strtotime($settings['validDate']);
            $validUntil = date('Y-m-d H:i:s', $validUntil);
        }

        if (!$dataAccess['maxCalls'] && !$validUntil) {
            throw new Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwordlink.create.no_limit_set'
            ));
        }

        $dataAccess = new HiddenString(json_encode($dataAccess));
        $dataAccess = SymmetricCrypto::encrypt($dataAccess, Utils::getSystemPasswordLinkKey());

        QUI::getDataBase()->insert(
            Tables::passwordLink(),
            array(
                'dataId'     => (int)$dataId,
                'dataAccess' => $dataAccess,
                'validUntil' => $validUntil
            )
        );

        $PasswordLink = new PasswordLink(QUI::getDataBase()->getPDO()->lastInsertId());

        \QUI\System\Log::writeRecursive($PasswordLink->getUrl());

        return $PasswordLink;
    }

    /**
     * Get list of PasswordLinks for a password
     *
     * @param int $passwordId
     * @return array
     */
    public static function getList($passwordId)
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id',
                'dataId',
                'validUntil',
                'active'
            ),
            'from'   => Tables::passwordLink(),
            'where'  => array(
                'dataId' => $passwordId
            )
        ));

        return $result;
    }
}
