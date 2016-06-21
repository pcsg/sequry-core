<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\CryptoUser
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Security\Interfaces\AuthPlugin;
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
class CryptoUserOld
{
    /**
     * User ID
     *
     * @var Integer
     */
    protected $_id = null;

    /**
     * Public keys for different auth methods
     *
     * @var array
     */
    protected $_publicKeys = array();

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

    /**
     * Current Authentication Plugin used for de/encryption
     *
     * @var null
     */
    protected $_AuthPlugin = null;

    /**
     * @param $userId
     * @param AuthPlugin $AuthPlugin (optional) - Authentication Plugin used for de/encryption
     */
    public function __construct($userId, AuthPlugin $AuthPlugin = null)
    {
        // check if QUIQQER user exists
        QUI::getUsers()->get($userId);

        // check if crypto user is session user
        if ($userId === QUI::getUserBySession()->getId()) {
            $this->_isCurrentUser = true;
        }

        if (!is_null($AuthPlugin)) {
            $this->_AuthPlugin = $AuthPlugin;
        }

        $this->_id = $userId;
    }

    /**
     * Set Authentication Plugin for this CryptoUser
     *
     * @param AuthPlugin $AuthPlugin
     */
    public function setAuthPlugin(AuthPlugin $AuthPlugin)
    {
        $this->_AuthPlugin = $AuthPlugin;
    }

    /**
     * Get currently set Authentication Plugin
     *
     * @return AuthPlugin
     * @throws QUI\Exception
     */
    protected function _getAuthPlugin()
    {
        if (empty($this->_AuthPlugin)) {
            throw new QUI\Exception('No authentication plugin set.');
        }

        return $this->_AuthPlugin;
    }

    /**
     * Generate a new key pair for this user. The private key will be encrypted
     * with a symmetric key depending on the Authentication Plugin set by setAuthPlugin()
     *
     * Needs to re-encrypt every password entry in database for a specific key pair
     * if existing key pair is changed!
     *
     * @return Boolean - success
     * @throws QUI\Exception
     */
    public function generateKeyPair()
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

        try {
            // Generate key pair
            $keyPair = AsymmetricCrypto::generateKeyPair();

            // Encrypt new private key
            $privateKeyEncrypted = $this->_symmetricEncrypt(
                $keyPair['privateKey']
            );
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

//        $this->_publicKeys[$authType] = $keyPair['publicKey'];

        // Clear private key from memory
        \Sodium\memzero($keyPair['privateKey']);

        $authName = $this->_getAuthPlugin()->getName();

        try {
            $insert = array(
                'publicKey' => $keyPair['publicKey'],
                'privateKey' => $privateKeyEncrypted,
                'authType' => $this->_getAuthPlugin()->getName()
            );

            $values = array();

            foreach ($insert as $key => $value) {
                $values[] = ':' . $key;
            }

            $sql = "INSERT INTO " . Manager::TBL_USERS;
            $sql .= " (`userId`, `" . implode("`, `",
                    array_keys($insert)) . "`)";
            $sql .= " VALUES('$this->_id', " . implode(",", $values) . ")";
            $sql .= " ON DUPLICATE KEY UPDATE ";

            $update = array();

            foreach ($insert as $column => $value) {
                $update[] =  "`" . $column . "`=`" . $column . "`";
            }

            $sql .= implode(",", $update);

            $PDO = QUI::getDataBase()->getPDO();
            $Stmt = $PDO->prepare($sql);

            foreach ($insert as $key => $value) {
                $Stmt->bindValue(':' . $key, $value);
            }

            $Stmt->execute();
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

        $this->_publicKeys[$authName] = $keyPair['publicKey'];

        return true;
    }

    /**
     * Encrypts a string with the users public key
     *
     * @param String $plainText - Text to be encrypted
     * @return String $cipherText - Encrypted plaintext
     * @throws QUI\Exception
     */
    public function publicKeyEncrypt($plainText)
    {
        $publicKey = $this->getPublicKey();

        try {
            $cipherText = AsymmetricCrypto::encrypt($plainText, $publicKey);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') ::'
                . 'Could not encrypt ciphertext with public key -> '
                . $Exception->getMessage(),
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
    public function privateKeyDecrypt($cipherText)
    {
        if (!$this->_isCurrentUser) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') ::'
                . 'Could not decrypt ciphertext -> CryptoUser is not'
                . ' the user that is currently logged in.',
                1003
            );
        }

        $privateKey = $this->_getPrivateKey();

        try {
            $plainText = AsymmetricCrypto::decrypt($cipherText, $privateKey);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') ::'
                . ' Could not decrypt ciphertext with private key -> '
                . $Exception->getMessage(),
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

        // delete private key from memory
        \Sodium\memzero($privateKey);

        return $plainText;
    }

    /**
     * Encrypts a plain text with the users symmetric private key
     *
     * @param String $plainText - The plaintext to be encrypted
     * @return String - The ciphertext
     * @throws QUI\Exception
     */
    protected function _symmetricEncrypt($plainText)
    {
        if (!$this->_isCurrentUser) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') ::'
                . 'Could not symmetrically encrypt plaintext -> CryptoUser is'
                . ' not the user that is currently logged in.',
                1008
            );
        }

        $symmetricKey = $this->_getEncryptionKey();

        try {
            $cipherText = SymmetricCrypto::encrypt($plainText, $symmetricKey);
        } catch (QUI\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') ::'
                . 'Could not symmetrically encrypt plaintext -> '
                . $Exception->getMessage(),
                1008
            );
        }

        // Delete symmetric key from memory
        \Sodium\memzero($symmetricKey);

        return $cipherText;
    }

    /**
     * Encrypts a plain text with the users symmetric private key
     *
     * @param String $cipherText - The ciphertext to be decrypted
     * @return String - The ciphertext
     * @throws QUI\Exception
     */
    protected function _symmetricDecrypt($cipherText)
    {
        if (!$this->_isCurrentUser) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') ::'
                . 'Could not symmetrically encrypt plaintext -> CryptoUser is'
                . ' not the user that is currently logged in.',
                1008
            );
        }

        $symmetricKey = $this->_getEncryptionKey();

        try {
            $plainText = SymmetricCrypto::decrypt($cipherText, $symmetricKey);
        } catch (QUI\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') ::'
                . 'Could not symmetrically decrypt ciphertext -> '
                . $Exception->getMessage(),
                1008
            );
        }

        // Delete symmetric key from memory
        \Sodium\memzero($symmetricKey);

        return $plainText;
    }

    /**
     * Get public key dependant on auth type
     *
     * @return String
     * @throws QUI\Exception
     */
    public function getPublicKey()
    {
        $authName = $this->_getAuthPlugin()->getName();

        if (isset($this->_publicKeys[$authName])
            && !empty($this->_publicKeys[$authName])) {
            return $this->_publicKeys[$authName];
        }

        // get public key
        try {
            $result = QUI::getDataBase()->fetch(
                array(
                    'select' => array(
                        'publicKey'
                    ),
                    'from' => Manager::TBL_USERS,
                    'where' => array(
                        'userId' => $this->_id,
                        'authType' => $authName
                    )
                )
            );
        } catch (QUI\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Could not fetch public'
                . ' key for auth plugin "' . $authName . '" from database: '
                . $Exception->getMessage(),
                1007
            );
        }

        if (empty($result)
            || !isset($result[0]['publicKey'])
            || empty($result[0]['publicKey'])) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Public key for auth plugin'
                . '"' . $authName . '" was not found or empty.',
                1007
            );
        }

        $this->_publicKeys[$authName] = $result[0]['publicKey'];

        return $result[0]['publicKey'];
    }

    /**
     * Get private key dependant on auth type
     *
     * @return String
     * @throws QUI\Exception
     */
    protected function _getPrivateKey()
    {
        $authName = $this->_getAuthPlugin()->getName();

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
                        'authType' => $authName
                    )
                )
            );
        } catch (QUI\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Could not fetch private'
                . ' key for auth plugin "' . $authName . '" from database: '
                . $Exception->getMessage(),
                1007
            );
        }
        
        if (empty($result)
            || !isset($result[0]['privateKey'])
            || empty($result[0]['privateKey'])) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Private key for auth plugin'
                . '"' . $authName . '" was not found or empty.',
                1007
            );
        }

        // decrypt private key
        $decryptedPrivateKey = $this->_symmetricDecrypt(
            $result[0]['privateKey']
        );

        if (empty($decryptedPrivateKey)) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Decrypted private key'
                . ' for auth plugin "' . $authName . '" was empty.',
                1007
            );
        }

        return $decryptedPrivateKey;
    }

    /**
     * Returns a symmetric encryption key based on auth type
     *
     * @return String - Key generated by auth plugin
     * @throws QUI\Exception
     */
    protected function _getEncryptionKey()
    {
        try {
            $encryptionKey = $this->_getAuthPlugin()->getKey();
        } catch (QUI\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Error retrieving'
                . ' symmetric key from authentification plugin -> '
                . $Exception->getMessage(),
                1006
            );
        }

        return $encryptionKey;
    }

    /**
     * Get an decrypted CryptoData object
     *
     * @param Integer $dataId - CryptoData ID in database
     * @param Array $authInformation - Information for authentication plugins
     * @return CryptoData
     * @throws QUI\Exception
     */
    public function getCryptoData($dataId, $authInformation)
    {
        $dataId = (int)$dataId;

        // get encrypted cryptodata key from database
        try {
            $result = QUI::getDataBase()->fetch(array(
                'select' => array(
                    'authLevel'
                ),
                'from' => Manager::TBL_CRYPTODATA,
                'where' => array(
                    'userId' => $this->_id,
                    'dataId' => $dataId
                ),
                'limit' => 1
            ));
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Could not retrieve'
                . ' database entry for cryptodata (#' . $dataId
                . '): ' . $Exception->getMessage(),
                1005
            );
        }

        if (empty($result)) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Could not retrieve'
                . ' database entry for crpyto data -> CryptoData with ID #'
                . $dataId . ' does not exist.',
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

        $authLevel = $result[0]['authLevel'];
        $authPlugins = CryptoAuth::getAuthPluginsByAuthLevel($authLevel);

        // get key parts for every auth plugin
        try {
            $result = QUI::getDataBase()->fetch(array(
                'select' => array(
                    'dataKey',
                    'authPlugin'
                ),
                'from' => Manager::TBL_USER_CRYPTODATA,
                'where' => array(
                    'userId' => $this->_id,
                    'dataId' => $dataId,
                    'authPlugin' => array(
                        'type' => 'IN',
                        'value' => array_keys($authPlugins)
                    )
                )
            ));
        } catch (\Exception $Exception) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Could not retrieve'
                . ' database entry for cryptodata user keys (cryptodata id#'
                . $dataId . '): ' . $Exception->getMessage(),
                1005
            );
        }

        if (empty($result) || count($result) !== count($authPlugins)) {
            throw new QUI\Exception(
                'CryptoUser (#' . $this->_id . ') :: Not enough encrypted'
                . ' key parts found for cryptodata #' . $dataId . ' (expected: '
                . count($authPlugins) . '; found: ' . count($result) . '). '
                . 'User has no access right for this crypto data.',
                1005
            );
        }

        // assemble cryptodata key
        $keyParts = array();

        foreach ($result as $row) {
            $this->setAuthPlugin($row['authPlugin']);
            $keyParts[] = $this->privateKeyDecrypt($row['dataKey']);
        }

        $key = Utils::joinKeyParts($keyParts);

        return new CryptoData($dataId, $key);
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