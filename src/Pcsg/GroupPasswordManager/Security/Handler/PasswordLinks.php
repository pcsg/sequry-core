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

        $CreateUser = QUI::getUserBySession();

        $dataAccess = array(
            'password'       => $password,
            'hash'           => \Sodium\bin2hex($hash),
            'dataKey'        => \Sodium\bin2hex($passwordKey),
            'createDate'     => date('Y-m-d H:i:s'),
            'createUserId'   => $CreateUser->getId(),
            'createUserName' => $CreateUser->getName(),
            'callCount'      => 0,
            'calls'          => array(),
            'maxCalls'       => false,
            'validUntil'     => false
        );

        // determine how long the link is valid

        // valid until a specific date
        if (!empty($settings['validDate'])) {
            $validUntil = strtotime($settings['validDate']);
            $validUntil = date('Y-m-d H:i:s', $validUntil);

            $dataAccess['validUntil'] = $validUntil;
        }

        // valid until a number of calls has been reached
        if (!empty($settings['maxCalls'])) {
            $dataAccess['maxCalls'] = (int)$settings['maxCalls'];
        }

        if (!$dataAccess['maxCalls'] && !$dataAccess['validUntil']) {
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
                'dataAccess' => $dataAccess
            )
        );

        $PasswordLink = new PasswordLink(QUI::getDataBase()->getPDO()->lastInsertId());

        return $PasswordLink;
    }

    /**
     * Get a PasswordLink
     *
     * @param int $id
     * @return PasswordLink
     */
    public static function get($id)
    {
        return new PasswordLink((int)$id);
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
            ),
            'from'   => Tables::passwordLink(),
            'where'  => array(
                'dataId' => $passwordId
            )
        ));

        $list = array();

        foreach ($result as $row) {
            $PasswordLink = self::get($row['id']);
            $list[]       = $PasswordLink->toArray();
        }

        return $list;
    }
}
