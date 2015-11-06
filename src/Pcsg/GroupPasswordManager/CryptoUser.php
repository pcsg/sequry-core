<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\CryptoUser
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
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
     * Hashed user password for private key
     *
     * @var String
     */
    protected $_pwHash = null;

    /**
     * Is the user in the user_passwords table?
     *
     * @var bool
     */
    protected $_hasDbEntry = false;

    /**
     * Is this the current session user?
     *
     * @var bool
     */
    protected $_isCurrentUser = false;

    public function __construct($userId)
    {
        // check if user exists
        $User = QUI::getUsers()->get($userId);

        if ($userId === QUI::getUserBySession()->getId()) {
            $this->_isCurrentUser = true;
        }

        if ($this->_isCurrentUser) {
            $this->_pwHash = QUI::getSession()->get($this::ATTRIBUTE_PWHASH);

            if (empty($this->_pwHash)) {
                // @todo Status-Code? 5XX?
                throw new QUI\Exception(
                    QUI::getLocale()->get(
                        'pcsg/grouppasswordmanager',
                        'exception.cryptouser.no.pw.hash',
                        array('userId' => $this->_id)
                    )
                );
            }
        }

        $this->_id = $userId;

        // get crypto user data
        try {
            $result = QUI::getDataBase()->fetch(array(
                'select' => array(
                    'public_key',
                    'private_key'
                ),
                'from' => Manager::TBL_USERS,
                'where' => array(
                    'user_id' => $this->_id
                ),
                'limit' => 1
            ));
        } catch (\Exception $Exception) {
            // nothing
        }

        if (!empty($result)) {
            $this->_hasDbEntry = true;
        }

        if (isset($result[0]['public_key'])
            && !empty($result[0]['public_key'])) {
            $this->_publicKey = $result[0]['public_key'];
        }

        if (isset($result[0]['private_key'])
            && !empty($result[0]['private_key'])) {
            $this->_publicKey = $result[0]['private_key'];
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
    public function generateKeyPair()
    {
        if (!$this->_isCurrentUser) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.cryptouser.generatekeypair.user.not.logged.in',
                    array('userId' => $this->_id)
                )
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

        if (!$this->_testKeyPair()) {
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

        // @todo einkommentieren!
//        $this->_reEncryptPasswordKeys();

        try {
            if ($this->_hasDbEntry) {
                QUI::getDataBase()->update(
                    Manager::TBL_USERS,
                    array(
                        'public_key' => $this->_publicKey,
                        'private_key' => $this->_privateKey
                    ),
                    array(
                        'user_id' => $this->_id
                    )
                );
            } else {
                QUI::getDataBase()->insert(
                    Manager::TBL_USERS,
                    array(
                        'user_id' => $this->_id,
                        'public_key' => $this->_publicKey,
                        'private_key' => $this->_privateKey
                    )
                );

                $this->_hasDbEntry = true;
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'User #' . $this->_id . ' could save key pair in users table: '
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

    public function setKeyPair()
    {

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
                    'password_key'
                ),
                'where' => array(
                    'user_id' => $this->_id
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

        return $rnd === $rndDecrypted;
    }
}