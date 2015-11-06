<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Password
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
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
     * User ID of initial Password creator (owner)
     *
     * @var Integer
     */
    protected $_ownerId = null;

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
        $encryptedData = $password['password_data'];

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

            // integrity check
            // @todo überarbeiten
            if (!isset($plainText['payload'])
                || !isset($plainText['hash'])
                || empty($plainText['hash'])
                || !isset($plainText['ownerId'])
                || empty($plainText['ownerId'])) {
                throw new QUI\Exception(
                    'Tha plaintext array did not contain the expected keys.'
                );
            }

            $this->_payload = $plainText['payload'];

            $hash = $plainText['hash'];
            $newHash = $this->_getPayloadHash();

            if ($hash !== $newHash) {
                throw new QUI\Exception(
                    'The sha256 hashes did not match.'
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

        $this->_key = $key;

        $this->setAttributes(array(
            'title' => $password['title'],
            'description' => $password['description']
        ));
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

        $this->_payload = $payload;
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
     * Return payload hash for verifying data integrity
     *
     * @return string
     */
    protected function _getPayloadHash()
    {
        return hash('sha256', json_encode($this->_payload));
    }
}