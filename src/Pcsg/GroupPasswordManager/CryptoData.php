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
 * CryptoData Class
 *
 * Represents sensitive information that is stored encrypted.
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class CryptoData extends QUI\QDOM
{
    /**
     * Permission to create a new CryptoData object
     */
    const PERMISSION_CREATE = 'pcsg.gpm.cryptodata.create';

    /**
     * Permission to edit a CryptoData object
     */
    const PERMISSION_EDIT = 'pcsg.gpm.cryptodata.edit';

    /**
     * Permission to delete a CryptoData object
     */
    const PERMISSION_DELETE = 'pcsg.gpm.cryptodata.delete';

    /**
     * Permission to set access rights for a CryptoData object (users and groups)
     */
    const PERMISSION_SETACCESS = 'pcsg.gpm.cryptodata.setaccess';

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
     * CryptoData payload - this is the information that is encrypted
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
     * User ID of initial CryptoData creator (owner)
     *
     * @var Integer
     */
    protected $_ownerId = null;

    /**
     * Auth level of this CryptoData
     *
     * @var String
     */
    protected $_authLevel = null;

    /**
     * constructor
     *
     * @param Integer $id - crpyotdata id in database
     * @param String $key - Symmetric encryption key
     * @throws QUI\Exception
     */
    public function __construct($id, $key)
    {
        $DB = QUI::getDataBase();

        // get password entry from database
        $result = $DB->fetch(array(
            'from' => Manager::TBL_CRYPTODATA,
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

        // try to decrypt the data and check for successful decryption
        try {
            $plainText = SymmetricCrypto::decrypt($encryptedData, $key);
            $plainText = json_decode($plainText, true);

            // check for json error
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new QUI\Exception(
                    'json_decode() error: ' . json_last_error_msg()
                );
            }

            if ($plainText['authLevel'] !== $password['authLevel']) {
                // @todo unerlaubte manipulation der datenbank -> ALERT system
                throw new QUI\Exception(
                    'AuthLevel does not match (should be: "'
                    . $plainText['authLevel'] . '" | is: "'
                    . $password['authLevel'] . '".'
                );
            }

        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoData (#' . $id . ') could not be decrypted: '
                . $Exception->getMessage()
            );

            // @todo auslagern
//            throw new QUI\Exception(
//                QUI::getLocale()->get(
//                    'pcsg/grouppasswordmanager',
//                    'exception.password.decryption.error',
//                    array('passwordId' => $id)
//                ),
//                1001
//            );
        }

        $this->_ownerId = $plainText['ownerId'];
        $this->_payload = $plainText['payload'];
        $this->_authLevel = $plainText['authLevel'];
        $this->_key = $key;

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
            'authLevel' => $this->_authLevel
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

            QUI::getDataBase()->update(
                Manager::TBL_CRYPTODATA,
                array(
                    'title' => $this->getAttribute('title'),
                    'description' => $this->getAttribute('description'),
                    'cryptoData' => SymmetricCrypto::encrypt(
                        $password,
                        $this->_key
                    ),
                    'authLevel' => $this->_authLevel
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
     * Return the auth type of this CryptoData
     *
     * @return String
     */
    public function getAuthLevel()
    {
        return $this->_authLevel;
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
     * Adds a user that can decrypt this CryptoData object
     *
     * @param \Pcsg\GroupPasswordManager\CryptoUser $CryptoUser
     * @return Boolean - success
     * @throws QUI\Exception
     */
    public function addUser($CryptoUser)
    {
        // Check permission
        $disposingUserId = QUI::getUserBySession()->getId();
        $receivingUserId = $CryptoUser->getId();

        $permission = true;

        try {
            QUI\Rights\Permission::checkPermission(
                self::PERMISSION_SETACCESS
            );
        } catch (QUI\Exception $Exception) {
            $permission = false;
        }

        if ($disposingUserId !== $this->_ownerId
            && $permission === false) {
            throw new QUI\Exception(
                'CryptoData (#' . $this->_id . ') :: User has no permission'
                . ' to add users.',
                1011
            );

            // @todo auslagern
//                throw new QUI\Exception(
//                    QUI::getLocale()->get(
//                        'pcsg/grouppasswordmanager',
//                        'exception.password.addviewuser.no.rights',
//                        array('passwordId' => $this->_id)
//                    ),
//                    1001 // @todo korrekten error code
//                );
        }

        try {
            // get authentification plugins
            $authPlugins = CryptoAuth::getAuthPluginsByAuthLevel(
                $this->_authLevel
            );

            // split keys into parts according to numbers of authPlugins used
            $keyParts = Utils::splitKey($this->_key, count($authPlugins));

            // encrypt each part with the users public key for the corresponding auth plugin
            foreach ($keyParts as $k => $keyPart) {
                $CryptoUser->setAuthPlugin(key($authPlugins));
                $CryptoUser->publicKeyEncrypt($keyPart);
            }

            $encryptedPasswordKey = $CryptoUser->publicKeyEncrypt(
                $this->_key
            );

            QUI::getDataBase()->insert(
                Manager::TBL_USER_CRYPTODATA,
                array(
                    'userId' => $receivingUserId,
                    'passwordId' => $this->_id,
                    'passwordKey' => $encryptedPasswordKey
                )
            );
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoData (#' . $this->_id . ') :: Could not add user to'
                . ' password -> ' . $Exception->getMessage(),
                1012
            );

            // @todo auslagern
//            throw new QUI\Exception(
//                QUI::getLocale()->get(
//                    'pcsg/grouppasswordmanager',
//                    'exception.password.addviewuser.error',
//                    array(
//                        'passwordId' => $this->_id,
//                        'userId' => $disposingUserId
//                    )
//                ),
//                1001 // @todo korrekten error code
//            );
        }
        
        return $this->save();
    }

    /**
     * Removes a user that can decrypt this CryptoData object
     *
     * @param \Pcsg\GroupPasswordManager\CryptoUser $CryptoUser
     * @return Boolean - success
     * @throws QUI\Exception
     */
    public function removeUser($CryptoUser)
    {
        // Check permission
        $disposingUserId = QUI::getUserBySession()->getId();
        $receivingUserId = $CryptoUser->getId();

        $permission = true;

        try {
            QUI\Rights\Permission::checkPermission(
                self::PERMISSION_SETACCESS
            );
        } catch (QUI\Exception $Exception) {
            $permission = false;
        }

        if ($disposingUserId !== $this->_ownerId
            && $permission === false) {
            throw new QUI\Exception(
                'CryptoData (#' . $this->_id . ') :: User has no permission'
                . ' to remove users.',
                1011
            );

            // @todo auslagern
//                throw new QUI\Exception(
//                    QUI::getLocale()->get(
//                        'pcsg/grouppasswordmanager',
//                        'exception.password.addviewuser.no.rights',
//                        array('passwordId' => $this->_id)
//                    ),
//                    1001 // @todo korrekten error code
//                );
        }

        try {
            QUI::getDataBase()->delete(
                Manager::TBL_USER_CRYPTODATA,
                array(
                    'userId' => $receivingUserId,
                    'dataId' => $this->_id
                )
            );
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoData (#' . $this->_id . ') :: Could not delete User #'
                . $receivingUserId . ' from ' . Manager::TBL_USER_CRYPTODATA
                . ' -> ' . $Exception->getMessage(),
                1011    // @todo korrekter error code
            );

            // @todo auslagern und korrekter error code
//            throw new QUI\Exception(
//                QUI::getLocale()->get(
//                    'pcsg/grouppasswordmanager',
//                    'exception.password.removeviewuser.error',
//                    array(
//                        'passwordId' => $this->_id,
//                        'userId' => $cryptoUserId
//                    )
//                ),
//                500
//            );
        }

        return $this->save();
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
                Manager::TBL_CRYPTODATA,
                array(
                    'id' => $this->_id
                )
            );

            // delete user entries for password
            QUI::getDataBase()->delete(
                Manager::TBL_USER_CRYPTODATA,
                array(
                    'passwordId' => $this->_id
                )
            );

            // @todo delete group entries

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