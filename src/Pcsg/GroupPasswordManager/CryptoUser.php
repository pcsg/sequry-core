<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\CryptoUser
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use QUI;

/**
 * User Class
 *
 * Represents a password manager User that can retrieve encrypted passwords
 * if it has the necessary rights.
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class CryptoUser
{
    const ATTRIBUTE_PWHASH = 'pcsg_gpw_pwhash';

    /**
     * User ID
     *
     * @var Integer
     */
    protected $_id = null;

    /**
     * User public key
     *
     * @var String
     */
    protected $_publicKey = null;

    /**
     * User private key (protected)
     *
     * @var String
     */
    protected $_privateKey = null;

    /**
     * Hashed user password for private key (only if crypto user is logged in user)
     *
     * @var String
     */
    protected $_pwHash = null;

    /**
     * Is this the current session user?
     *
     * @var bool
     * @throws QUI\Exception
     */
    protected $_isCurrentUser = false;

    public function __construct($userId)
    {
        // check if QUIQQER user exists
        QUI::getUsers()->get($userId);

        // check if crypto user is session user
        if ($userId === QUI::getUserBySession()->getId()) {
            $this->_isCurrentUser = true;
        }

        if ($this->_isCurrentUser) {
            $this->_pwHash = QUI::getSession()->get($this::ATTRIBUTE_PWHASH);

            if (empty($this->_pwHash)) {
                throw new QUI\Exception(
                    QUI::getLocale()->get(
                        'pcsg/grouppasswordmanager',
                        'exception.cryptouser.no.pw.hash',
                        array('userId' => $this->_id)
                    ),
                    500
                );
            }
        }

        $this->_id = $userId;

        // get crypto user data
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'publicKey',
                'privateKey'
            ),
            'from' => Manager::TBL_USERS,
            'where' => array(
                'userId' => $this->_id
            ),
            'limit' => 1
        ));

        // generate key pair if this user does not already have one
        if (empty($result)) {
            $this->generateKeyPair();
        }

        if (isset($result[0]['publicKey'])
            && !empty($result[0]['publicKey'])) {
            $this->_publicKey = $result[0]['publicKey'];
        }

        if (isset($result[0]['privateKey'])
            && !empty($result[0]['privateKey'])) {
            $this->_privateKey = $result[0]['privateKey'];
        }
    }

    /**
     * Encrypts a string with the users public key
     *
     * @param String $plainText - Text to be encrypted
     * @return String $cipherText - Encrypted plaintext
     * @throws QUI\Exception
     */
    public function encrypt($plainText)
    {
        if (empty($this->_publicKey)) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptouser.encrypt.no.public.key',
                    array('userId' => $this->_id)
                )
            );
        }

        try {
            $cipherText = AsymmetricCrypto::encrypt(
                $plainText,
                $this->_publicKey
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'User #' . $this->_id . ' could not encrypt data with public'
                . ' key: ' . $Exception->getMessage()
            );

            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptouser.encrypt.error',
                    array('userId' => $this->_id)
                )
            );
        }

        return $cipherText;
    }

    /**
     * Decrypts a ciphertext with the users private key
     *
     * @param String $cipherText - The encrypted plaintext
     * @return String - The decrypted plaintext
     * @throws QUI\Exception
     */
    public function decrypt($cipherText)
    {
        if (empty($this->_privateKey)) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptouser.encrypt.no.private.key',
                    array('userId' => $this->_id)
                )
            );
        }

        try {
            $plainText = AsymmetricCrypto::decrypt(
                $cipherText,
                $this->_privateKey,
                $this->_pwHash
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'User #' . $this->_id . ' could not decrypt data with private key'
                . ' key: ' . $Exception->getMessage()
            );

            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptouser.decrypt.error',
                    array('userId' => $this->_id)
                )
            );
        }

        return $plainText;
    }

    /**
     * Generate a new key pair for this user
     *
     * Needs to re-encrypt every password access line in database
     *
     * @return Boolean - success
     * @throws QUI\Exception
     */
    public function generateKeyPair($password = null)
    {
        if (!$this->_isCurrentUser) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptouser.generatekeypair.user.not.logged.in',
                    array('userId' => $this->_id)
                ),
                401
            );
        }

        try {
            $keyPair = AsymmetricCrypto::generateKeyPair($this->_pwHash);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'User #' . $this->_id . ' could generate new key pair: '
                . $Exception->getMessage()
            );

            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptouser.generatekeypair.error',
                    array('userId' => $this->_id)
                )
            );
        }

        $this->_publicKey = $keyPair['publicKey'];
        $this->_privateKey = $keyPair['privateKey'];

        if (!$this->_testKeyPair()) {
            QUI\System\Log::addError(
                'User #' . $this->_id . ' could generate new key pair: '
                . ' key pair test failed.'
            );

            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptouser.generatekeypair.error',
                    array('userId' => $this->_id)
                )
            );
        }

        // @todo einkommentieren!
//        $this->_reEncryptPasswordKeys();

        try {
            $insert = array(
                'publicKey' => $this->_publicKey,
                'privateKey' => $this->_privateKey
            );

            $sql = "INSERT INTO " . Manager::TBL_USERS;
            $sql .= " (`userId`, `" . implode("`, `", array_keys($insert)) . "`)";
            $sql .= " VALUES('$this->_id', " . implode("', '", $insert) . "')";
            $sql .= " ON DUPLICATE KEY UPATE ";

            $update = "";

            foreach ($insert as $column => $value) {
                $update = "`" . $column . "`=`" . $column . "`";
            }

            $sql .= implode("`, `", $update);

            QUI::getDataBase()->getPDO()->exec($sql);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'User #' . $this->_id . ' could not save key pair in users table: '
                . $Exception->getMessage()
            );

            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptouser.generatekeypair.error',
                    array('userId' => $this->_id)
                )
            );
        }

        return true;
    }

    /**
     * @todo
     *
     * Get passwords the user has access to
     *
     * @param $filter
     * @return array
     */
    public function getPasswordList($filter)
    {
        // @todo filter einbauen (sortieren, suche)
        try {
            $result = QUI::getDataBase()->fetch(array(
                'select' => array(
                    ''
                )
            ));
        } catch (\Exception $Exception) {

        }


    }

    /**
     * Returns user id
     *
     * @return Integer
     */
    public function getId()
    {
        return $this->_id;
    }

    public function setKeyPair()
    {

    }

    /**
     * Returns public key of user
     *
     * @return String
     */
    public function getPublicKey()
    {
        return $this->_publicKey;
    }

    /**
     * Returns protected private key of user
     *
     * @return String
     */
    public function getPrivateKey()
    {
        return $this->_privateKey;
    }

    /**
     * Get an encrypted Password object
     *
     * @param Integer $passwordId - Password ID in database
     * @param String $passphrase - Password for protected user private key
     * @return Password
     * @throws QUI\Exception
     */
    public function getPassword($passwordId, $passphrase)
    {
        try {
            $result = QUI::getDataBase()->fetch(array(
                'select' => array(
                    'passwordKey'
                ),
                'from' => Manager::TBL_USER_PASSWORDS,
                'where' => array(
                    'userId' => $this->_id,
                    'passwordId' => $passwordId
                ),
                'limit' => 1
            ));
        } catch (\Exception $Exception) {

        }

        if (empty($result)) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptouser.getpassword.no.right',
                    array('passwordId' => $passwordId)
                )
            );
        }

        $key = AsymmetricCrypto::decrypt(
            $result[0]['passwordKey'],
            $this->_privateKey,
            $passphrase
        );

        return new Password($passwordId, $key);
    }

    /**
     * @todo
     *
     * Re-encrypts all password keys with the current public key of this user
     */
    protected function _reEncryptPasswordKeys()
    {
        try {
            $result = QUI::getDataBase()->fetch(array(
                'select' => array(
                    'id',
                    'passwordKey'
                ),
                'where' => array(
                    'userId' => $this->_id
                )
            ));
        } catch (\Exception $Exception) {

        }


    }

    /**
     * Checks if the currently set key pair is valid
     *
     * @return Boolean - validity of the key pair
     */
    protected function _testKeyPair()
    {
        $rnd = openssl_random_pseudo_bytes(10);

        $rndEncrypted = $this->encrypt($rnd);
        $rndDecrypted = $this->decrypt($rndEncrypted);

        return Utils::compareStrings($rnd, $rndDecrypted);
    }
}