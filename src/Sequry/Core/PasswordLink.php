<?php

namespace Sequry\Core;

use ParagonIE\Halite\Alerts\InvalidKey;
use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Security\Handler\PasswordLinks;
use QUI;
use Sequry\Core\Exception\Exception;
use Sequry\Core\Security\Handler\Passwords;
use Sequry\Core\Security\HiddenString;
use Sequry\Core\Security\Keys\Key;
use Sequry\Core\Security\SymmetricCrypto;
use Sequry\Core\Security\Utils;
use Sequry\Core\Constants\Tables;
use Sequry\Core\Security\KDF;
use Sequry\Core\Security\Exception\InvalidKeyException;

/**
 * Class PasswordLink
 *
 * Handles creation and access to linked passwords
 */
class PasswordLink
{
    const SITE_TYPE = 'sequry/core:types/passwordlink';

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
     * The password the link points to
     *
     * @var Password
     */
    protected $Password;

    /**
     * PasswordLink constructor.
     *
     * @param int $id - PasswordLink ID
     *
     * @throws \Sequry\Core\Exception\Exception
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
                'sequry/core',
                'exception.passwordlink.not_found'
            ), 404);
        }

        $data = current($result);

        $this->id              = (int)$id;
        $this->dataId          = $data['dataId'];
        $this->encryptedAccess = $data['dataAccess'];
        $this->active          = boolval($data['active']);
        $this->Password        = Passwords::get($this->dataId);

        // if PasswordLink is no longer valid -> delete it
        try {
            $this->decode();
        } catch (\Exception $Exception) {
            throw new Exception(array(
                'sequry/core',
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
     * @throws \Sequry\Core\Exception\Exception
     */
    public function getUrl()
    {
        $Project = false;

        if (!empty($this->access['vhost'])) {
            $VHostManager = new QUI\System\VhostManager();
            $Project      = $VHostManager->getProjectByHost($this->access['vhost']);
        }

        if (!$Project) {
            $Project = QUI::getProjectManager()->getStandard();
        }

        $sites = $Project->getSites(array(
            'where' => array(
                'type' => self::SITE_TYPE
            ),
            'limit' => 1
        ));

        if (empty($sites)) {
            throw new Exception(array(
                'sequry/core',
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
     * Get PasswordLink ID
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Decode and validate PasswordLink data
     *
     * IMPORTANT:   If invalid data is detected the PasswordLink is automatically deactivated
     *              If the access data is corrupted (cannot be decrypted) the PasswordLink is deleted
     *
     * @return void
     *
     * @throws \Sequry\Core\Exception\Exception
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
                'sequry/core',
                'exception.passwordlink.decryption_failed'
            ));
        }

        $access = json_decode($access->getString(), true);

        $access['hash']           = \Sodium\hex2bin($access['hash']);
        $access['encryptionSalt'] = \Sodium\hex2bin($access['encryptionSalt']);
        $access['dataKey']        = new HiddenString(\Sodium\hex2bin($access['dataKey']));
        $this->access             = $access;

        // check date
        if ($access['validUntil']) {
            $validUntil = strtotime($access['validUntil']);

            if (time() > $validUntil) {
                $this->deactivate();
            }
        }

        // check current number of calls
        if ($access['maxCalls']
            && $access['callCount'] >= $access['maxCalls']
        ) {
            $this->deactivate();
        }
    }

    /**
     * Get Password data
     *
     * @param string $hash - Correct hash for this PasswordLink
     * @param string $decryptPass (optional) - decryption password (if PasswordLink is password protected)
     * @return Password
     *
     * @throws \Sequry\Core\Exception\Exception
     * @throws InvalidKeyException
     */
    public function getPassword($hash, $decryptPass = null)
    {
        if (!$this->active) {
            throw new Exception(array(
                'sequry/core',
                'exception.passwordlink.not_active'
            ));
        }

        if (!hash_equals(\Sodium\hex2bin($hash), $this->access['hash'])) {
            throw new Exception(array(
                'sequry/core',
                'exception.passwordlink.invalid_hash'
            ));
        }

        if ($this->isPasswordProtected() && is_null($decryptPass)) {
            throw new Exception(array(
                'sequry/core',
                'exception.passwordlink.no_password_given'
            ));
        }

        $dataKey = $this->access['dataKey']->getString();

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

            $this->Password->decrypt($DataKey);
        } catch (\Exception $Exception) {
            throw new InvalidKeyException();
        }

        // if session user has permission to view password -> do not increase counter
        $SessionUser = QUI::getUserBySession();

        if ($SessionUser instanceof QUI\Users\User
            && $this->Password->hasPasswordAccess(CryptoActors::getCryptoUser())) {
            return $this->Password;
        }

        // increase call counter
        $this->access['callCount']++;

        // get call(er) data
        $callData = array(
            'date' => date('Y-m-d H:i:s')
        );

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $callData['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
        }

        $this->access['calls'][] = $callData;

        // save access data changes
        $this->update();

        return $this->Password;
    }

    /**
     * Get title
     *
     * @return false|string
     */
    public function getContentTitle()
    {
        return $this->access['title'];
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
     * Get date until the link is valid
     *
     * @return \DateTime|false
     */
    public function getValidUntil()
    {
        $date = $this->access['validUntil'];

        if (empty($date)) {
            return false;
        }

        return new \DateTime($date);
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
        $access = $this->access;

        if (empty($this->access['dataKey'])) {
            $access['dataKey'] = null;
        } else {
            $access['dataKey'] = \Sodium\bin2hex($this->access['dataKey']->getString());
        }

        $access['hash']           = \Sodium\bin2hex($this->access['hash']);
        $access['encryptionSalt'] = \Sodium\bin2hex($this->access['encryptionSalt']);
        $access                   = new HiddenString(json_encode($access));
        $access                   = SymmetricCrypto::encrypt($access, Utils::getSystemPasswordLinkKey());

        QUI::getDataBase()->update(
            Tables::passwordLink(),
            array(
                'active'     => $this->isActive(),
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
     * @param bool $checkPermission (optional) - check PasswordLink permission [default: true]
     * @return void
     */
    public function deactivate($checkPermission = true)
    {
        if ($checkPermission !== false) {
            $this->checkPermission();
        }

        $this->access['dataKey'] = null;
        $this->active            = false;

        $this->update();
    }

    /**
     * Permanently delete PasswordLink
     *
     * @return void
     */
    public function delete()
    {
        $this->checkPermission();

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
     * Check if the current session user has permission to edit this PasswordLink
     *
     * @return void
     * @throws Exception
     */
    protected function checkPermission()
    {
        if (!PasswordLinks::isUserAllowedToUsePasswordLinks($this->Password)) {
            throw new Exception(array(
                'sequry/core',
                'exception.passwordlink.permission_denied'
            ));
        }
    }

    /**
     * Get PasswordLink attributes as array
     *
     * @return array
     */
    public function toArray()
    {
        // Password owner
        $passwordOwnerId = $this->access['passwordOwnerId'];

        switch ((int)$this->access['passwordOwnerType']) {
            case Password::OWNER_TYPE_USER:
                try {
                    $PasswordOwner = CryptoActors::getCryptoUser($passwordOwnerId);
                    $passwordOwner = $PasswordOwner->getUsername() . ' (#' . $PasswordOwner->getId() . ')';
                } catch (\Exception $Exception) {
                    $passwordOwner = '#' . $passwordOwnerId;
                }

                $passwordOwnerType = 'user';
                break;

            default:
                try {
                    $PasswordOwner = CryptoActors::getCryptoGroup($passwordOwnerId);
                    $passwordOwner = $PasswordOwner->getName() . ' (#' . $PasswordOwner->getId() . ')';
                } catch (\Exception $Exception) {
                    $passwordOwner = '#' . $passwordOwnerId;
                }

                $passwordOwnerType = 'group';
        }

        // SecurityClass
        try {
            $SecurityClass = Authentication::getSecurityClass($this->access['securityClassId']);
            $securityClass = $SecurityClass->getAttribute('title');
        } catch (\Exception $Exception) {
            $securityClass = '#' . $this->access['securityClassId'];
        }

        return array(
            'id'                => $this->id,
            'validUntil'        => $this->access['validUntil'],
            'callCount'         => $this->access['callCount'],
            'maxCalls'          => $this->access['maxCalls'],
            'password'          => $this->access['password'],
            'createDate'        => $this->access['createDate'],
            'createUserId'      => $this->access['createUserId'],
            'createUserName'    => $this->access['createUserName'],
            'calls'             => $this->access['calls'],
            'vhost'             => $this->access['vhost'],
            'active'            => $this->isActive(),
            'link'              => $this->getUrl(),
            'passwordOwnerType' => $passwordOwnerType,
            'passwordOwner'     => $passwordOwner,
            'securityClass'     => $securityClass
        );
    }
}
