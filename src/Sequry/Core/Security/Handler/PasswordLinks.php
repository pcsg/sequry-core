<?php

/**
 * This file contains \Sequry\Core\Security\Handler\PasswordLinks
 */

namespace Sequry\Core\Security\Handler;

use Sequry\Core\Actors\CryptoGroup;
use Sequry\Core\Actors\CryptoUser;
use Sequry\Core\Constants\Permissions;
use Sequry\Core\Constants\Tables;
use Sequry\Core\Password;
use Sequry\Core\Security\HiddenString;
use Sequry\Core\Security\KDF;
use Sequry\Core\Security\SymmetricCrypto;
use Sequry\Core\Security\Utils;
use QUI;
use Sequry\Core\PasswordLink;
use Sequry\Core\Security\Hash;
use Sequry\Core\Security\Random;
use Sequry\Core\Exception\Exception;
use QUI\Utils\Security\Orthos;

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
     * @throws \Sequry\Core\Exception\Exception
     */
    public static function create($dataId, $settings = array())
    {
        // check if Password is eligible for linking
        $Password = Passwords::get($dataId);

        if (!$Password->getSecurityClass()->isPasswordLinksAllowed()) {
            throw new Exception(array(
                'sequry/core',
                'exception.security.handler.passwordlinks.securityclass_links_not_allowed'
            ));
        }

        if (!self::isUserAllowedToUsePasswordLinks($Password)) {
            throw new Exception(array(
                'sequry/core',
                'exception.security.handler.passwordlinks.no_permission'
            ));
        }

        // create link data
        $hash = Hash::create(
            new HiddenString(Random::getRandomData())
        );

        $passwordKey    = $Password->getPasswordKey()->getValue()->getString();
        $password       = false;
        $encryptionSalt = false;

        // additionally encrypt password data with an access password
        if (!empty($settings['password'])) {
            $encryptionSalt = Random::getRandomData();

            $passwordKey = SymmetricCrypto::encrypt(
                new HiddenString($passwordKey),
                KDF::createKey(
                    new HiddenString($settings['password']),
                    $encryptionSalt
                )
            );

            $password = true;
        }

        $CreateUser = QUI::getUserBySession();

        $dataAccess = array(
            'password'          => $password,
            'encryptionSalt'    => \Sodium\bin2hex($encryptionSalt),
            'hash'              => \Sodium\bin2hex($hash),
            'dataKey'           => \Sodium\bin2hex($passwordKey),
            'createDate'        => date('Y-m-d H:i:s'),
            'createUserId'      => $CreateUser->getId(),
            'createUserName'    => $CreateUser->getName(),
            'callCount'         => 0,
            'calls'             => array(),
            'maxCalls'          => false,
            'validUntil'        => false,
            'title'             => empty($settings['title']) ? false : $settings['title'],
            'message'           => empty($settings['message']) ? false : $settings['message'],
            'vhost'             => false,
            'passwordOwnerId'   => $Password->getOwner()->getId(),
            'passwordOwnerType' => $Password->getOwnerType(),
            'securityClassId'   => $Password->getSecurityClass()->getId()
        );

        // vhost
        if (!empty($settings['vhost'])) {
            $dataAccess['vhost'] = $settings['vhost'];
        }

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
                'sequry/core',
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

        if (!empty($settings['email'])) {
            $recipients = str_replace(' ', '', $settings['email']);
            $recipients = explode(',', $recipients);

            self::sendPasswordLinkMail($PasswordLink, $recipients);
        }

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
     * Get all PasswordLinks by Password ID
     *
     * @param int $passwordId
     * @return PasswordLink[]
     */
    public static function getLinksByPasswordId($passwordId)
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id',
            ),
            'from'   => Tables::passwordLink(),
            'where'  => array(
                'dataId' => (int)$passwordId
            )
        ));

        $passwordLinks = array();

        foreach ($result as $row) {
            $passwordLinks[] = self::get($row['id']);
        }

        return $passwordLinks;
    }

    /**
     * Get list of PasswordLinks for a password
     *
     * @param int $passwordId
     * @param array $searchParams (optional)
     * @param bool $countOnly (optional) - get count only
     * @return array
     */
    public static function getList($passwordId, $searchParams = array(), $countOnly = false)
    {
        // ORDER BY
        $order = '`id`';

        if (!empty($searchParams['sortOn'])) {
            $order = '`' . Orthos::clear($searchParams['sortOn']) . '`';
        }

        if (!empty($searchParams['sortBy'])) {
            $order .= " " . Orthos::clear($searchParams['sortBy']);
        } else {
            $order .= " DESC";
        }

        // LIMIT
        $limit = null;

        if (!empty($gridParams['limit'])
            && !$countOnly
        ) {
            $limit = $gridParams['limit'];
        } elseif (!$countOnly) {
            $limit = 20;
        }

        $where = array(
            'dataId' => $passwordId,
            'active' => 1
        );

        if (!empty($searchParams['showInactive'])) {
            unset($where['active']);
        }

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id',
            ),
            'from'   => Tables::passwordLink(),
            'where'  => $where,
            'order'  => $order,
            'limit'  => $limit
        ));

        if ($countOnly) {
            return count($result);
        }

        $list = array();

        foreach ($result as $row) {
            $PasswordLink = self::get($row['id']);
            $list[]       = $PasswordLink->toArray();
        }

        return $list;
    }

    /**
     * Check if an actor is allowed to use PasswordLinks for a specific password
     *
     * @param Password $Password
     * @param CryptoUser|CryptoGroup $Actor
     * @return bool
     */
    public static function isActorAllowedToUsePasswordLinks(Password $Password, $Actor)
    {
        if ($Actor instanceof CryptoUser) {
            return self::isUserAllowedToUsePasswordLinks($Password, $Actor);
        }

        return self::isGroupAllowedToUsePasswordLinks($Password, $Actor);
    }

    /**
     * Check if a CryptoGroup is allowed to use PasswordLinks for a specific password
     *
     * @param Password $Password
     * @param CryptoGroup $Group
     * @return bool
     */
    public static function isGroupAllowedToUsePasswordLinks(Password $Password, CryptoGroup $Group)
    {
        if (!$Password->getSecurityClass()->isPasswordLinksAllowed()) {
            return false;
        }

        $PasswordOwner = $Password->getOwner();

        if ($PasswordOwner instanceof CryptoGroup
            && $PasswordOwner->getId() !== $Group->getId()) {
            return false;
        }

        return $Group->hasPermission(Permissions::PASSWORDLINKS_ALLOWED);
    }

    /**
     * Check is a CryptoUser is allowed to use PasswordLinks for a specific password
     *
     * @param Password $Password
     * @param CryptoUser $User (optional) - if omitted user session user
     * @return bool
     */
    public static function isUserAllowedToUsePasswordLinks(Password $Password, CryptoUser $User = null)
    {
        if (is_null($User)) {
            $User = CryptoActors::getCryptoUser();
        }

        if (!$Password->getSecurityClass()->isPasswordLinksAllowed()) {
            return false;
        }

        $PasswordOwner = $Password->getOwner();

        if ($PasswordOwner instanceof CryptoUser) {
            return $PasswordOwner->getId() === $User->getId();
        }

        /** @var CryptoGroup $PasswordOwner */
        if (!$User->isInGroup($PasswordOwner->getId())) {
            return false;
        }

        return QUI\Permissions\Permission::hasPermission(
            Permissions::PASSWORDLINKS_ALLOWED,
            $User
        );
    }

    /**
     * Send PasswordLink via mail(s)
     *
     * @param PasswordLink $PasswordLink
     * @param array $recipients - Mail adresses
     * @return void
     */
    protected static function sendPasswordLinkMail(PasswordLink $PasswordLink, $recipients)
    {
        if (empty($recipients)) {
            return;
        }

        $Mailer = new QUI\Mail\Mailer();
        $Engine = QUI::getTemplateManager()->getEngine();
        $L      = QUI::getLocale();
        $lg     = 'sequry/core';

        $Engine->assign(array(
            'greeting' => $L->get($lg, 'mail.passwordlink.greeting'),
            'body'     => $L->get($lg, 'mail.passwordlink.body', array(
                'url' => $PasswordLink->getUrl()
            ))
        ));

        $Mailer->setHTML($Engine->fetch(
            QUI::getPackage($lg)->getDir() . 'templates/mail_passwordlink.html'
        ));

        $Mailer->setSubject($L->get($lg, 'mail.passwordlink.subject'));

        foreach ($recipients as $recipient) {
            $Mailer->addRecipient($recipient);
        }

        try {
            $Mailer->send();
        } catch (\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());

            QUI::getMessagesHandler()->addAttention(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.security.handler.passwordlinks.mail_send_error'
                )
            );

            return;
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'sequry/core',
                'message.security.handler.passwordlinks.mail_send_success'
            )
        );
    }
}