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
     * User private key
     *
     * @var String
     */
    protected $_privateKey = null;

    /**
     * Public and Private keys for different auth methods
     *
     * @var array
     */
    protected $_keys = array();

    /**
     * Encryption keys for different auth methods
     *
     * @var array
     */
    protected $_encryptionKeys = array();

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
            && !empty($result[0]['publicKey'])
        ) {
            $this->_publicKey = $result[0]['publicKey'];
        }

        if (isset($result[0]['privateKey'])
            && !empty($result[0]['privateKey'])
        ) {
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
                'CryptoUser (#' . $this->_id . ') ::'
                . 'Could not encrypt ciphertext -> No public key defined.',
                1002
            );

            // @todo auslagern
//            throw new QUI\Exception(
//                QUI::getLocale()->get(
//                    'pcsg/grouppasswordmanager',
//                    'exception.cryptouser.encrypt.no.public.key',
//                    array('userId' => $this->_id)
//                )
//            );
        }

        try {
            $cipherText = AsymmetricCrypto::encrypt(
                $plainText,
                $this->_publicKey
            );
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') ::'
                . 'Could not encrypt ciphertext -> ' . $Exception->getMessage(),
                1002
            );

            // @todo auslagern
//            throw new QUI\Exception(
//                QUI::getLocale()->get(
//                    'pcsg/grouppasswordmanager',
//                    'exception.cryptouser.encrypt.error',
//                    array('userId' => $this->_id)
//                )
//            );
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
                'CryptoUser (#' . $this->_id . ') ::'
                . 'Could not decrypt ciphertext -> No private key defined.',
                1003
            );

            // @todo auslagern an benutzerschnittstelle
//            throw new QUI\Exception(
//                QUI::getLocale()->get(
//                    'pcsg/grouppasswordmanager',
//                    'exception.cryptouser.encrypt.no.private.key',
//                    array('userId' => $this->_id)
//                )
//            );
        }

        if (!$this->_isCurrentUser) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') ::'
                . 'Could not decrypt ciphertext -> CryptoUser is not'
                . ' the user that is currently logged in.',
                1003
            );
        }

        try {
            $plainText = AsymmetricCrypto::decrypt(
                $cipherText,
                $this->_privateKey,
                $this->_pwHash
            );
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') ::'
                . ' Could not decrypt ciphertext -> ' . $Exception->getMessage(),
                1003
            );

            // @todo auslagern an benutzerschnittstelle
//            throw new QUI\Exception(
//                QUI::getLocale()->get(
//                    'pcsg/grouppasswordmanager',
//                    'exception.cryptouser.decrypt.error',
//                    array('userId' => $this->_id)
//                )
//            );
        }

        return $plainText;
    }

    /**
     * Generate a new key pair for this user. The private key will be encrypted
     * depending on the authType:
     *
     * - default: KDF derived key from User login password
     *
     * Needs to re-encrypt every password access line in database if existing
     * key pair is changed!
     *
     * @return Boolean - success
     * @throws QUI\Exception
     *
     * @todo Implement other auth types (secure passphrase, fingerprint, yubikey etc.)
     */
    public function generateKeyPair($authType = 'default')
    {
        // Check if this CryptoUser is the currently logged in User
        if (!$this->_isCurrentUser) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Cannot generate key pair ->'
                . ' The CryptoUser is not the currently logged in user.',
                1004
            );

//            throw new QUI\Exception(
//                QUI::getLocale()->get(
//                    'pcsg/grouppasswordmanager',
//                    'exception.cryptouser.generatekeypair.user.not.logged.in',
//                    array('userId' => $this->_id)
//                ),
//                401
//            );
        }

        // Generate key pair
        try {
            $keyPair = AsymmetricCrypto::generateKeyPair();
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Could not generate key pair'
                . ': ' . $Exception->getMessage(),
                1004
            );

            // @todo auslagern
//            throw new QUI\Exception(
//                QUI::getLocale()->get(
//                    'pcsg/grouppasswordmanager',
//                    'exception.cryptouser.generatekeypair.error',
//                    array('userId' => $this->_id)
//                )
//            );
        }

        // encrypt private key depending on authType
        try {
            $privateKeyEncrypted = SymmetricCrypto::encrypt(
                $keyPair['privateKey'],
                $this->_pwHash
            );
        } catch (QUI\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Could not generate key pair'
                . ': Private key could not be encrypted.',
                1004
            );
        }


        // @todo einkommentieren!
//        $this->_reEncryptPasswordKeys();

        $this->_publicKey  = $keyPair['publicKey'];
        $this->_privateKey = $privateKeyEncrypted;

        try {
            $insert = array(
                'publicKey' => $this->_publicKey,
                'privateKey' => $this->_privateKey
            );

            $sql = "INSERT INTO " . Manager::TBL_USERS;
            $sql .= " (`userId`, `" . implode("`, `",
                    array_keys($insert)) . "`)";
            $sql .= " VALUES('$this->_id', " . implode("', '", $insert) . "')";
            $sql .= " ON DUPLICATE KEY UPATE ";

            $update = "";

            foreach ($insert as $column => $value) {
                $update = "`" . $column . "`=`" . $column . "`";
            }

            $sql .= implode("`, `", $update);

            QUI::getDataBase()->getPDO()->exec($sql);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Could not generate key pair'
                . ' -> Could not save key pair in users table: '
                . $Exception->getMessage(),
                1004
            );

            // @todo auslagern
//            throw new QUI\Exception(
//                QUI::getLocale()->get(
//                    'pcsg/grouppasswordmanager',
//                    'exception.cryptouser.generatekeypair.error',
//                    array('userId' => $this->_id)
//                )
//            );
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
     * Get public key dependant on auth type
     *
     * @param String $authType (optional) - auth type [default: default]
     * @return String
     * @throws QUI\Exception
     */
    public function getPublicKey($authType = 'default')
    {
        if (isset($this->_keys[$authType]['public'])
            && !empty($this->_encryptionKeys[$authType]['public'])) {
            return $this->_encryptionKeys[$authType]['public'];
        }

        switch ($authType) {
            case 'default':
                break;
            default:
                $authType = 'default';
                break;
        }

        // get encrypted private key
        try {
            $result = QUI::getDataBase()->fetch(
                array(
                    'select' => array(
                        'publicKey'
                    ),
                    'from' => Manager::TBL_USERS,
                    'where' => array(
                        'userId' => $this->_id,
                        'authType' => $authType
                    )
                )
            );
        } catch (QUI\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Could not fetch public'
                . ' key for auth type "' . $authType . '" from database: '
                . $Exception->getMessage(),
                1007
            );
        }

        if (empty($result)
            || !isset($result[0]['publicKey'])
            || empty($result[0]['publicKey'])) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Public key for auth type'
                . '"' . $authType . '" was not found or empty.',
                1007
            );
        }

        $this->_keys[$authType]['private'] = $result[0]['publicKey'];

        return $result[0]['publicKey'];
    }

    /**
     * Get private key dependant on auth type
     *
     * @param String $authType (optional) - auth type [default: default]
     * @return String
     * @throws QUI\Exception
     */
    protected function _getPrivateKey($authType = 'default')
    {
        if (isset($this->_keys[$authType]['private'])
            && !empty($this->_keys[$authType]['private'])) {
            return $this->_keys[$authType]['private'];
        }

        switch ($authType) {
            case 'default':
                break;
            default:
                $authType = 'default';
                break;
        }

        // get encrypted private key
        try {
            $result = QUI::getDataBase()->fetch(
                array(
                    'select' => array(
                        'privateKey'
                    ),
                    'from' => Manager::TBL_USERS,
                    'where' => array(
                        'userId' => $this->_id,
                        'authType' => $authType
                    )
                )
            );
        } catch (QUI\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Could not fetch private'
                . ' key for auth type "' . $authType . '" from database: '
                . $Exception->getMessage(),
                1007
            );
        }
        
        if (empty($result)
            || !isset($result[0]['privateKey'])
            || empty($result[0]['privateKey'])) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Private key for auth type'
                . '"' . $authType . '" was not found or empty.',
                1007
            );
        }

        // decrypt private key
        $encryptionKey = $this->_getEncryptionKey($authType);
        
        try {
            $decryptedPrivateKey = SymmetricCrypto::decrypt(
                $result[0]['privateKey'],
                $encryptionKey
            );
        } catch (QUI\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Could not decrypt private'
                . 'key for auth type "' . $authType . '".',
                1007
            );
        }

        if (empty($decryptedPrivateKey)) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Decrypted private key'
                . ' for auth type "' . $authType . '" was empty.',
                1007
            );
        }

        $this->_keys[$authType]['private'] = $decryptedPrivateKey;

        return $decryptedPrivateKey;
    }

    /**
     * Returns a symmetric encryption key based on auth type
     *
     * @param String $authType (optional) - default: "default"
     * @return String
     * @throws QUI\Exception
     */
    protected function _getEncryptionKey($authType = 'default')
    {
        if (isset($this->_encryptionKeys[$authType])
            && !empty($this->_encryptionKeys[$authType])) {
            return $this->_encryptionKeys[$authType];
        }

        switch ($authType) {
            case 'default':
            default:
                $key = QUI::getSession()->get($this::ATTRIBUTE_PWHASH);
                break;
        }

        if (empty($key)) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Symmetric key for auth'
                . ' type "' . $authType . '" could not be found or was empty.'
                . ' Please re-generate it.',
                1006
            );
        }

        $this->_encryptionKeys[$authType] = $key;

        return $key;
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
        // get encrypted password key from database
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
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Could not retrieve'
                . ' database entry for user password (#' . $passwordId
                . '): ' . $Exception->getMessage(),
                1005
            );
        }

        if (empty($result)) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Could not retrieve'
                . ' database entry for user password -> User has no access'
                . ' rights (no data entry exists).',
                1005
            );

            // @todo auslagern
//            throw new QUI\Exception(
//                QUI::getLocale()->get(
//                    'pcsg/grouppasswordmanager',
//                    'exception.cryptouser.getpassword.no.right',
//                    array('passwordId' => $passwordId)
//                )
//            );
        }

        // get auth type for password


        // decrypt private key
        $key = AsymmetricCrypto::decrypt(
            $result[0]['passwordKey'],
            $this->_privateKey,
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
}