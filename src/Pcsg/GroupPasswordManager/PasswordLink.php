<?php

namespace Pcsg\GroupPasswordManager;

use QUI;
use Pcsg\GroupPasswordManager\Exception\Exception;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use Pcsg\GroupPasswordManager\Security\HiddenString;
use Pcsg\GroupPasswordManager\Security\Keys\Key;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Security\KDF;
use Pcsg\GroupPasswordManager\Security\Exception\InvalidKeyException;

/**
 * Class PasswordLink
 *
 * Handles creation and access to linked passwords
 */
class PasswordLink
{
    const SITE_TYPE = 'pcsg/grouppasswordmanager:types/passwordlink';

    /**
     * @var int
     */
    protected $id;

    /**
     * ID of linked Password
     *
     * @var int
     */
    protected $dataId;

    /**
     * @var string
     */
    protected $encryptedAccess;

    /**
     * Access data
     *
     * @var array
     */
    protected $access;

    /**
     * @var Key
     */
    protected $DataKey;

    /**
     * @var bool
     */
    protected $active;

    /**
     * PasswordLink constructor.
     *
     * @param int $id - PasswordLink ID
     *
     * @throws \Pcsg\GroupPasswordManager\Exception\Exception
     */
    public function __construct($id)
    {
        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::passwordLink(),
            'where' => array(
                'id' => (int)$id
            )
        ));

        if (empty($result)) {
            throw new Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwordlink.not_found'
            ), 404);
        }

        $data = current($result);

        $this->id              = (int)$id;
        $this->dataId          = $data['dataId'];
        $this->encryptedAccess = $data['dataAccess'];
        $this->active          = boolval($data['active']);

        // if PasswordLink is no longer valid -> delete it
        try {
            $this->decode();
        } catch (\Exception $Exception) {
            throw new Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwordlink.invalid',
                array(
                    'error' => $Exception->getMessage()
                )
            ));
        }
    }

    /**
     * Get URL for the PasswordLink
     *
     * @return string
     *
     * @throws \Pcsg\GroupPasswordManager\Exception\Exception
     */
    public function getUrl()
    {
        $Project = QUI::getProjectManager()->getStandard();

        $sites = $Project->getSites(array(
            'where' => array(
                'type' => self::SITE_TYPE
            ),
            'limit' => 1
        ));

        if (empty($sites)) {
            throw new Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwordlink.site_not_found'
            ));
        }

        /** @var QUI\Projects\Site $Site */
        $Site = current($sites);

        $url = $Project->getVHost(true);
        $url .= $Site->getUrlRewritten(array(), array(
            'id'   => $this->id,
            'hash' => \Sodium\bin2hex($this->access['hash'])
        ));

        return $url;
    }

    /**
     * Decode and validate PasswordLink data
     *
     * IMPORTANT:   If invalid data is detected the PasswordLink is automatically deactivated
     *              If the access data is corrupted (cannot be decrypted) the PasswordLink is deleted
     *
     * @return void
     *
     * @throws \Pcsg\GroupPasswordManager\Exception\Exception
     */
    protected function decode()
    {
        // decrypt access data
        try {
            $access = SymmetricCrypto::decrypt(
                $this->encryptedAccess,
                Utils::getSystemPasswordLinkKey()
            );
        } catch (\Exception $Exception) {
            $this->delete();

            throw new Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwordlink.decryption_failed'
            ));
        }

        $access = json_decode($access->getString(), true);

        // check date
        if ($access['validUntil']) {
            $validUntil = strtotime($access['validUntil']);

            if ($validUntil > time()) {
                $this->deactivate();

//                throw new Exception(array(
//                    'pcsg/grouppasswordmanager',
//                    'exception.passwordlink.no_longer_valid'
//                ));
            }
        }

        // check current number of calls
        if ($access['callCount'] >= $access['maxCalls']) {
            $this->deactivate();

//            throw new Exception(array(
//                'pcsg/grouppasswordmanager',
//                'exception.passwordlink.max_calls_reached'
//            ));
        }

        $access['hash']           = \Sodium\hex2bin($access['hash']);
        $access['encryptionSalt'] = \Sodium\hex2bin($access['encryptionSalt']);
        $access['dataKey']        = new HiddenString(\Sodium\hex2bin($access['dataKey']));
        $this->access             = $access;
    }

    /**
     * Get Password data
     *
     * @param string $hash - Correct hash for this PasswordLink
     * @param string $decryptPass (optional) - decryption password (if PasswordLink is password protected)
     * @return Password
     *
     * @throws \Pcsg\GroupPasswordManager\Exception\Exception
     */
    public function getPassword($hash, $decryptPass = null)
    {
        if (!$this->active) {
            throw new Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwordlink.not_active'
            ));
        }

        if (!hash_equals(\Sodium\hex2bin($hash), $this->access['hash'])) {
            throw new Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwordlink.invalid_hash'
            ));
        }

        if ($this->isPasswordProtected() && is_null($decryptPass)) {
            throw new Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwordlink.no_password_given'
            ));
        }

        $Password = Passwords::get($this->dataId);
        $dataKey  = $this->access['dataKey']->getString();

        try {
            if ($this->isPasswordProtected()) {
                $dataKey = SymmetricCrypto::decrypt(
                    $dataKey,
                    KDF::createKey(
                        new HiddenString($decryptPass),
                        $this->access['encryptionSalt']
                    )
                );
            }

            $DataKey = new Key(new HiddenString($dataKey));

            $Password->decrypt($DataKey);
        } catch (\Exception $Exception) {
            throw new InvalidKeyException();
        }

        // increase call counter
        $this->access['callCount']++;

        // get call(er) data
        $callData = array(
            'userId'   => QUI::getUserBySession()->getId(),
            'userName' => QUI::getUserBySession()->getName(),
            'date'     => date('Y-m-d H:i:s')
        );

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $callData['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
        }

        $this->access['calls'][] = $callData;

        // save access data changes
        $this->update();

        return $Password;
    }

    /**
     * Get message
     *
     * @return false|string
     */
    public function getContentMessage()
    {
        return $this->access['message'];
    }

    /**
     * Checks if this PasswordLink is protected by a password
     *
     * @return bool
     */
    public function isPasswordProtected()
    {
        return $this->access['password'];
    }

    /**
     * Update PasswordLink
     *
     * @return void
     */
    protected function update()
    {
        $access                   = $this->access;
        $access['dataKey']        = \Sodium\bin2hex($this->access['dataKey']->getString());
        $access['hash']           = \Sodium\bin2hex($this->access['hash']);
        $access['encryptionSalt'] = \Sodium\bin2hex($this->access['encryptionSalt']);
        $access                   = new HiddenString(json_encode($access));
        $access                   = SymmetricCrypto::encrypt($access, Utils::getSystemPasswordLinkKey());

        QUI::getDataBase()->update(
            Tables::passwordLink(),
            array(
                'dataAccess' => $access
            ),
            array(
                'id' => $this->id
            )
        );
    }

    /**
     * Deactivate PasswordLink
     *
     * @return void
     */
    protected function deactivate()
    {
        QUI::getDataBase()->update(
            Tables::passwordLink(),
            array(
                'active' => 0
            ),
            array(
                'id' => $this->id
            )
        );

        $this->active = false;
    }

    /**
     * Permanently delete PasswordLink
     *
     * @return void
     */
    protected function delete()
    {
        QUI::getDataBase()->delete(
            Tables::passwordLink(),
            array(
                'id' => $this->id
            )
        );
    }

    /**
     * Check if this PasswordLink is active
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * Get PasswordLink attributes as array
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'id'             => $this->id,
            'validUntil'     => $this->access['validUntil'],
            'callCount'      => $this->access['callCount'],
            'maxCalls'       => $this->access['maxCalls'],
            'password'       => $this->access['password'],
            'createDate'     => $this->access['createDate'],
            'createUserId'   => $this->access['createUserId'],
            'createUserName' => $this->access['createUserName'],
            'calls'          => $this->access['calls'],
            'active'         => $this->isActive(),
            'link'           => $this->getUrl()
        );
    }
}
