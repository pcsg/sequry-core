<?php

namespace Pcsg\GroupPasswordManager;

use QUI;
use Pcsg\GroupPasswordManager\Exception\Exception;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use Pcsg\GroupPasswordManager\Security\Hash;
use Pcsg\GroupPasswordManager\Security\HiddenString;
use Pcsg\GroupPasswordManager\Security\Keys\Key;
use Pcsg\GroupPasswordManager\Security\Random;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use Pcsg\GroupPasswordManager\Constants\Tables;

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
     * @var string
     */
    protected $validUntil;

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
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => Tables::passwordLink(),
            'where'  => array(
                'dataId' => (int)$dataId
            )
        ));

        if (!empty($result)) {
            return new PasswordLink($result[0]['id']);
        }

        $Password = Passwords::get($dataId);

        // check if Password is eligible for linking

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
            'hash'     => $hash,
            'dataKey'  => $passwordKey,
            'calls'    => 0,
            'maxCalls' => 1 // default value
        );

        if (!empty($settings['maxCalls'])) {
            $dataAccess['maxCalls'] = (int)$settings['maxCalls'];
        }

        $dataAccess = new HiddenString(json_encode($dataAccess));
        $dataAccess = SymmetricCrypto::encrypt($dataAccess, Utils::getSystemPasswordLinkKey());

        // determine how long the link is valid
        if (empty($settings['validDate'])) {
            $now        = date('Y-m-d H:i:s');
            $validUntil = strtotime($now . ' +1 hour');
        } else {
            $validUntil = strtotime($settings['validDate']);
        }

        $validUntil = date('Y-m-d H:i:s', $validUntil);

        QUI::getDataBase()->insert(
            Tables::passwordLink(),
            array(
                'dataId'     => (int)$dataId,
                'dataAccess' => $dataAccess,
                'validUntil' => $validUntil
            )
        );

        return new PasswordLink(QUI::getDataBase()->getPDO()->lastInsertId());
    }

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
        $this->validUntil      = $data['validUntil'];

        // if PasswordLink is no longer valid -> delete it
        try {
            $this->validate();
        } catch (\Exception $Exception) {
            throw new Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwordlink.invalid'
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

        $url = $Project->getVHost(true) . '/';
        $url .= $Site->getUrlRewritten(array(), array(
            'id'   => $this->id,
            'hash' => $this->access['hash']
        ));

        return $url;
    }

    /**
     * Check if PasswordLink is still valid
     *
     * @return void
     *
     * @throws \Pcsg\GroupPasswordManager\Exception\Exception
     */
    protected function validate()
    {
        // check date
        $validUntil = strtotime($this->validUntil);

        if ($validUntil > time()) {
            $this->delete();

            throw new Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwordlink.no_longer_valid'
            ));
        }

        // decrypt access data
        try {
            $access = SymmetricCrypto::decrypt(
                $this->encryptedAccess,
                Utils::getSystemPasswordLinkKey()
            );
        } catch (\Exception $Exception) {
            throw new Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwordlink.decryption_failed'
            ));
        }

        $access = json_decode($access->getString(), true);

        // check access count
        if ($access['calls'] >= $access['maxCalls']) {
            $this->delete();

            throw new Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwordlink.max_calls_reached'
            ));
        }

        $access['dataKey'] = new Key(new HiddenString($access['dataKey']));
        $this->access      = $access;
    }

    /**
     * Get Password data
     *
     * @param string $hash - Correct hash for this PasswordLink
     * @return array - Password view data
     *
     * @throws \Pcsg\GroupPasswordManager\Exception\Exception
     */
    public function getPasswordData($hash)
    {
        if (!hash_equals($hash, $this->access['hash'])) {
            throw new Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwordlink.invalid_hash'
            ));
        }

        $Password = Passwords::get($this->dataId);
        $Password->decrypt($this->DataKey);

        // increase call counter
        $this->access['calls']++;
        $this->update();

        return $Password->getViewData();
    }

    /**
     * Update PasswordLink
     *
     * @return void
     */
    protected function update()
    {
        $access            = $this->access;
        $access['dataKey'] = $this->access['dataKey']->getValue()->getString();
        $access            = new HiddenString(json_encode($access));
        $access            = SymmetricCrypto::encrypt($access, Utils::getSystemPasswordLinkKey());

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
}
