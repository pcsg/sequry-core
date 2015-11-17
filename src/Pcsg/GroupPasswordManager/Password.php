<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Password
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use QUI;

/**
 * Password Class
 *
 * Represents secret information that is stored encrypted.
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Password extends QUI\QDOM
{
    /**
     * Password ID in database
     *
     * @var Integer
     */
    protected $_id = null;

    /**
     * Symmetric key for this password
     *
     * @var String
     */
    protected $_key = null;

    /**
     * Password payload - this is the information that is encrypted
     *
     * @var Array|String
     */
    protected $_payload = null;

    /**
     * New payload data
     *
     * @var Array|String
     */
    protected $_newPayload = null;

    /**
     * History of payload data
     *
     * @var null
     */
    protected $_history = array();

    /**
     * User ID of initial Password creator (owner)
     *
     * @var Integer
     */
    protected $_ownerId = null;

    /**
     * User IDs of every user that can view this password
     */
    protected $_viewUsers = array();

    /**
     * User IDs of every user that can edit this password
     *
     * @var array
     */
    protected $_editUsers = array();

    /**
     * constructor
     *
     * @param Integer $id - password id from database
     * @param String $key - Symmetric encryption key
     * @throws QUI\Exception
     */
    public function __construct($id, $key)
    {
        $DB = QUI::getDataBase();

        // get password entry from database
        $result = $DB->fetch(array(
            'from' => Manager::TBL_PASSWORDS,
            'where' => array(
                'id' => $id
            ),
            'limit' => 1
        ));

        if (empty($result)) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.not.found',
                    array('passwordId' => $id)
                ),
                404
            );
        }

        $password = current($result);

        $this->_id = $id;
        $encryptedData = $password['passwordData'];
        $mac = $password['passwordMac'];

        // try to decrypt the data and check for successful decryption
        try {
            $plainText = SymmetricCrypto::decrypt($encryptedData, $key);
            $macComputed = MAC::create($plainText, $key);

            // Check integrity and authenticity
            if (!Utils::compareStrings($mac, $macComputed)) {
                throw new QUI\Exception(
                    'The MAC hashes did not match.'
                );
            }

            $plainText = json_decode($plainText, true);

            // check for json error
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new QUI\Exception(
                    'json_decode() error: ' . json_last_error_msg()
                );
            }

        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Password #' . $id . ' could not be decrypted: '
                . $Exception->getMessage()
            );

            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.decryption.error',
                    array('passwordId' => $id)
                ),
                1001
            );
        }

        $this->_ownerId = $plainText['ownerId'];
        $this->_payload = $plainText['payload'];
        $this->_key = $key;

        if (isset($plainText['editUsers'])) {
            $this->_editUsers = $plainText['editUsers'];
        }

        if (isset($plainText['history'])) {
            $this->_history = $plainText['history'];
        }

        $this->setAttributes(array(
            'title' => $password['title'],
            'description' => $password['description']
        ));
    }

    /**
     * @todo maybe protected
     *
     * Save current password state to database
     *
     * @return Boolean - success
     * @throws QUI\Exception
     */
    public function save()
    {
        $password = array(
            'payload' => $this->_payload,
            'ownerId' => $this->_ownerId,
            'editUsers' => $this->_editUsers,
            'viewUsers' => $this->_viewUsers
        );

        if (!is_null($this->_newPayload)) {
            $this->_history[] = array(
                'timestamp' => time(),
                'payload' => $this->_payload
            );

            $password['payload'] = $this->_newPayload;
        }

        $password['history'] = $this->_history;

        try {
            $password = json_encode($password);

            // check for json error
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new QUI\Exception(
                    'json_decode() error: ' . json_last_error_msg()
                );
            }

            $mac = MAC::create($password, $this->_key);

            QUI::getDataBase()->update(
                Manager::TBL_PASSWORDS,
                array(
                    'title' => $this->getAttribute('title'),
                    'description' => $this->getAttribute('description'),
                    'passwordData' => SymmetricCrypto::encrypt(
                        $password,
                        $this->_key
                    ),
                    'passwordMac' => $mac
                ),
                array(
                    'id' => $this->_id
                )
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Password #' . $this->_id . ' could not be saved: '
                . $Exception->getMessage()
            );

            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.save.error',
                    array('passwordId' => $this->_id)
                ),
                1001 // @todo korrekter error code
            );
        }
        
        return true;
    }

    /**
     * Set a password title
     *
     * This is public and unencrypted information so DO NOT save anything
     * sensitive in here!
     *
     * @param String $title - Short, descriptive title for this password
     */
    public function setTitle($title)
    {

    }

    /**
     * Set sensitive data for this password (like login name / passphrase)
     *
     * This data will be encrypted, so ONLY USE this function for any
     * sensitive information!
     *
     * @param Array|String $data
     * @throws QUI\Exception
     * @see setPayload
     */
    public function setData($data)
    {
        $this->setPayload($data);
    }

    /**
     * Set sensitive data for this password (like login name / passphrase)
     *
     * This data will be encrypted, so ONLY USE this function for any
     * sensitive information!
     *
     * @param Array|String $payload
     * @throws QUI\Exception
     */
    public function setPayload($payload)
    {
        if (!is_array($payload)
            && !is_string($payload)) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.payload.wrong.datatype',
                    array('passwordId' => $this->_id)
                ),
                1001
            );
        }

        if ($this->_payload !== $payload) {
            $this->_newPayload = $payload;
        }
    }

    /**
     * Returns the database id of this password
     *
     * @return Integer
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Returns the private key that is used to encrypt this password
     *
     * @return String
     */
    public function getKey()
    {
        return $this->_key;
    }

    /**
     * Return user id of the password creator
     *
     * @return int
     */
    public function getOwnerId()
    {
        return $this->_ownerId;
    }

    /**
     * Sets a new owner for this password
     *
     * @param CryptoUser $User - New Owner
     * @return Boolean - success
     * @throws QUI\Exception
     */
    public function setOwner(CryptoUser $User)
    {
        $Setter = QUI::getUserBySession();

        // A new owner can only be set by the current owner, a super user or
        // if there is currently no owner
        if ($Setter->getId() !== $this->_ownerId
            && !$Setter->isSU()
            && !empty($this->_ownerId)) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.setowner.no.rights',
                    array('passwordId' => $this->_id)
                ),
                401
            );
        }

        $this->_ownerId = $User->getId();

        return $this->save();
    }

    /**
     * Return history data
     */
    public function getHistory()
    {
        return $this->_history;
    }

    /**
     * Return payload data
     *
     * @return Array|String
     */
    public function getPayload()
    {
        // @todo evtl. newPayload beachten?
        return $this->_payload;
    }

    /**
     * Adds a user that can decrypt this password
     *
     * @param \Pcsg\GroupPasswordManager\CryptoUser $CryptoUser
     * @return Boolean - success
     * @throws QUI\Exception
     */
    public function addViewUser($CryptoUser)
    {
        if (isset($this->_viewUsers[$CryptoUser->getId()])) {
            return true;
        }

        $userId = QUI::getUserBySession()->getId();
        $viewUserId = $CryptoUser->getId();

        if ($userId !== $this->_ownerId
            && !isset($this->_editUsers[$userId])) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.addviewuser.no.rights',
                    array('passwordId' => $this->_id)
                ),
                1001 // @todo korrekten error code
            );
        }

        try {
            // encrypt password key with user public key
            $encryptedPasswordKey = AsymmetricCrypto::encrypt(
                $this->_key,
                $CryptoUser->getPublicKey()
            );

            QUI::getDataBase()->insert(
                Manager::TBL_USER_PASSWORDS,
                array(
                    'userId' => $viewUserId,
                    'passwordId' => $this->_id,
                    'passwordKey' => $encryptedPasswordKey
                )
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Could not add view user to password #' . $this->_id . ': '
                . $Exception->getMessage()
            );

            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.addviewuser.error',
                    array(
                        'passwordId' => $this->_id,
                        'userId' => $viewUserId
                    )
                ),
                1001 // @todo korrekten error code
            );
        }
        
        $this->_viewUsers[$viewUserId] = true;

        return $this->save();
    }

    /**
     * @todo Kann Edit User View User entfernen!?
     *
     * Removes a user that can view the password
     *
     * @param \Pcsg\GroupPasswordManager\CryptoUser $CryptoUser
     * @return Boolean - success
     * @throws QUI\Exception
     */
    public function removeViewUser($CryptoUser)
    {
        $userId = QUI::getUserBySession()->getId();

        if ($userId !== $this->_ownerId) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.removeviewuser.no.rights',
                    array('passwordId' => $this->_id)
                ),
                401
            );
        }

        $cryptoUserId = $CryptoUser->getId();

        if (!isset($this->_viewUsers[$cryptoUserId])) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.removeviewuser.no.user',
                    array(
                        'passwordId' => $this->_id,
                        'userId' => $cryptoUserId
                    )
                ),
                420
            );
        }

        try {
            QUI::getDataBase()->delete(
                Manager::TBL_USER_PASSWORDS,
                array(
                    'userId' => $cryptoUserId
                )
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Could not delete user #' . $cryptoUserId . ' from '
                . Manager::TBL_USER_PASSWORDS . ': ' . $Exception->getMessage()
            );

            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.removeviewuser.error',
                    array(
                        'passwordId' => $this->_id,
                        'userId' => $cryptoUserId
                    )
                ),
                500
            );
        }

        unset($this->_viewUsers[$cryptoUserId]);

        return $this->save();
    }

    /**
     * Adds a user that can edit this password
     *
     * @param \Pcsg\GroupPasswordManager\CryptoUser $CryptoUser
     * @return Boolean - success
     * @throws QUI\Exception
     */
    public function addEditUser($CryptoUser)
    {
        $editUserId = $CryptoUser->getId();

        if (isset($this->_editUsers[$editUserId])) {
            return true;
        }

        $userId = QUI::getUserBySession()->getId();

        if ($userId !== $this->_ownerId) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.addedituser.no.rights',
                    array('passwordId' => $this->_id)
                ),
                401
            );
        }

        // if user has no view right -> add it first
        if (!isset($this->_viewUsers[$editUserId])) {
            $this->addViewUser($CryptoUser);
        }

        $this->_editUsers[$editUserId] = true;

        return $this->save();
    }

    /**
     * Removes a user that can edit the password
     *
     * @param \Pcsg\GroupPasswordManager\CryptoUser $CryptoUser
     * @throws QUI\Exception
     */
    public function removeEditUser($CryptoUser)
    {
        $userId = QUI::getUserBySession()->getId();

        if ($userId !== $this->_ownerId) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.removeedituser.no.rights',
                    array('passwordId' => $this->_id)
                ),
                401
            );
        }

        $cryptoUserId = $CryptoUser->getId();

        if (!isset($this->_editUsers[$cryptoUserId])) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.removeedituser.no.user',
                    array(
                        'passwordId' => $this->_id,
                        'userId' => $cryptoUserId
                    )
                ),
                401
            );
        }

        unset($this->_editUsers[$cryptoUserId]);
    }

    /**
     * Deletes the password
     *
     * @return Boolean - success
     * @throws QUI\Exception
     */
    public function delete()
    {
        $userId = QUI::getUserBySession()->getId();

        if ($userId !== $this->_ownerId) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.delete.no.rights',
                    array('passwordId' => $this->_id)
                ),
                401
            );
        }

        try {
            // delete password
            QUI::getDataBase()->delete(
                Manager::TBL_PASSWORDS,
                array(
                    'id' => $this->_id
                )
            );

            // delete user entries for password
            QUI::getDataBase()->delete(
                Manager::TBL_USER_PASSWORDS,
                array(
                    'passwordId' => $this->_id
                )
            );

        } catch (\Exception $Exception) {

            QUI\System\Log::addError(
                'Could not delete password #' . $this->_id . ': '
                . $Exception->getMessage()
            );

            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.delete.error',
                    array(
                        'passwordId' => $this->_id
                    )
                ),
                10000
            );
        }

        return true;
    }
}