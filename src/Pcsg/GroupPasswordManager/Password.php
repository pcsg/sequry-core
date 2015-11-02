<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Password
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Security\Encrypt;
use Pcsg\GroupPasswordManager\Security\Hash;
use QUI;

/**
 * Password Class
 *
 * Represents a secret passphrase and/or login information that is stored
 * encrypted.
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
     * Password data - this is the information that is encrypted
     *
     * @var Array|String
     */
    protected $_data = null;

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
            'from' => PasswordManager::TBL_PASSWORDS,
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
            $plainText = Encrypt::decrypt($encryptedData, $key);
            $plainText = json_decode($plainText, true);

            // check for json error
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new QUI\Exception(
                    'json_decode() error: ' . json_last_error_msg()
                );
            }

            // integrity check
            if (!isset($plainText['data'])
                || !isset($plainText['hash'])
                || empty($plainText['hash'])) {
                throw new QUI\Exception(
                    'Tha plaintext array did not contain the expected keys.'
                );
            }

            $hash = $plainText['hash'];
            $newHash = hash('sha256', json_encode($plainText['data']));

            if ($hash !== $newHash) {
                throw new QUI\Exception(
                    'The sha256 hashes did not match.'
                );
            }
        } catch (\Exception $Exception) {
            \QUI\System\Log::addError(
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

        $this->_data = $plainText['data'];
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
     * @param Array|String $data
     * @throws QUI\Exception
     */
    public function setData($data)
    {
        if (!is_array($data)
            && !is_string($data)) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.password.decryption.error',
                    array('passwordId' => $this->_id)
                ),
                1001
            );
        }
    }

}