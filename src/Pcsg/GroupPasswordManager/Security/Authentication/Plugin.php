<?php

namespace Pcsg\GroupPasswordManager\Security\Authentication;

use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\Interfaces\iAuthPlugin;
use Pcsg\GroupPasswordManager\Security\Keys\Key;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use QUI;

/**
 * This class is an internal represantion of an external authentication plugin
 */
class Plugin extends QUI\QDOM
{
    /**
     * ID of authentication plugin
     *
     * @var integer
     */
    protected $id = null;

    /**
     * External authentication plugin class
     *
     * @var iAuthPlugin
     */
    protected $AuthClass = null;

    /**
     * AuthPlugin constructor.
     *
     * @param integer $id - authentication plugin id
     * @throws QUI\Exception
     */
    public function __construct($id)
    {
        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::AUTH_PLUGINS,
            'where' => array(
                'id' => (int)$id
            )
        ));

        if (empty($result)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.auth.plugin.not.found',
                array(
                    'id' => $id
                )
            ), 404);
        }

        $data      = current($result);
        $classPath = $data['path'];

        try {
            $AuthClass = new $classPath();
        } catch (QUI\Exception $Exception) {
            throw new QUI\Exception(
                'Could not create instance of Authentication plugin #' . $id . ' class ->'
                . $Exception->getMessage()
            );
        }

        $this->AuthClass = $AuthClass;
        $this->id        = $data['id'];

        $this->setAttributes(array(
            'title'       => $data['title'],
            'description' => $data['description']
        ));
    }

    /**
     * Get ID of this plugin
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns a QUI\Control object that collects authentification information
     *
     * @return string - control URI for require.js
     */
    public function getAuthenticationControl()
    {
        return $this->AuthClass->getAuthenticationControl();
    }

    /**
     * Returns a QUI\Control object that collects registration information
     *
     * @return string - control URI for require.js
     */
    public function getRegistrationControl()
    {
        return $this->AuthClass->getRegistrationControl();
    }

    /**
     * Returns a QUI\Control object that allows changing of authentication information
     *
     * @return \QUI\Control
     */
    public function getChangeAuthenticationControl()
    {
        return $this->AuthClass->getChangeAuthenticationControl();
    }

    /**
     * Authenticates a user with a plugin
     *
     * @param mixed $information (optional) - authentication information
     * @return true - if authenticated
     * @throws QUI\Exception
     */
    public function authenticate($information = null)
    {
        return $this->AuthClass->authenticate($information);
    }

    /**
     * Checks if current session user is authenticated with this plugin
     *
     * @return bool
     */
    public function isAuthenticated()
    {
        return $this->AuthClass->isAuthenticated();
    }

    /**
     * Change authentication information
     *
     * @param mixed $old - current authentication information
     * @param mixed $new - new authentication information
     * @return void
     *
     * @throws QUI\Exception
     */
    public function changeAuthenticationInformation($old, $new)
    {
        $this->AuthClass->authenticate($old);

        $AuthKeyPair     = CryptoActors::getCryptoUser()->getAuthKeyPair($this);
        $publicKeyValue  = $AuthKeyPair->getPublicKey()->getValue();
        $privateKeyValue = $AuthKeyPair->getPrivateKey()->getValue();

        $this->AuthClass->changeAuthenticationInformation($old, $new);

        // encrypt auth private key with derived key from new authentication information
        $encryptedPrivateKeyValue = SymmetricCrypto::encrypt(
            $privateKeyValue,
            $this->AuthClass->getDerivedKey()
        );

        // calculate new MAC
        // @todo noch mehr informationen in den MAC einfließen lassen (plugin id, user id etc.)
        $macValue = MAC::create(
            $publicKeyValue . $encryptedPrivateKeyValue,
            Utils::getSystemKeyPairAuthKey()
        );

        try {
            // put everything in the database
            QUI::getDataBase()->update(
                Tables::KEYPAIRS,
                array(
                    'publicKey'  => $publicKeyValue,
                    'privateKey' => $encryptedPrivateKeyValue,
                    'MAC'        => $macValue
                ),
                array(
                    'userId'       => QUI::getUserBySession()->getId(),
                    'authPluginId' => $this->id
                )
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError(
                'DB error while changing auth info for a user for an auth plugin: ' . $Exception->getMessage()
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.auth.plugin.changeauth.database.error'
            ));
        }
    }

    /**
     * @todo auch für nicht session user
     *
     * Registers the current session user with this Plugin
     *
     * @param mixed $information (optional) - authentication information
     * @return void
     * @throws QUI\Exception
     */
    public function registerUser($information = null)
    {
        try {
            // register with plugin
            $this->AuthClass->register(QUI::getUserBySession(), $information);

            // authenticate with plugin
            $this->AuthClass->authenticate($information);

            // get derived key from authentication information
            $AuthKey = $this->AuthClass->getDerivedKey();
        } catch (QUI\Database\Exception $Exception) {
            QUI\System\Log::addError(
                'DB error while registering a user for an auth plugin: ' . $Exception->getMessage()
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.auth.plugin.register.database.error'
            ));
        }

        // if authentication information is correct, create new keypair for user
        $KeyPair = AsymmetricCrypto::generateKeyPair();

        // encrypt private key
        $publicKeyValue           = $KeyPair->getPublicKey()->getValue();
        $encryptedPrivateKeyValue = SymmetricCrypto::encrypt(
            $KeyPair->getPrivateKey()->getValue(),
            $AuthKey
        );

        // calculate MAC with system auth key
        // @todo noch mehr informationen in den MAC einfließen lassen (plugin id, user id etc.)
        $macValue = MAC::create(
            $publicKeyValue . $encryptedPrivateKeyValue,
            Utils::getSystemKeyPairAuthKey()
        );

        try {
            // put everything in the database
            QUI::getDataBase()->insert(
                Tables::KEYPAIRS,
                array(
                    'userId'       => QUI::getUserBySession()->getId(),
                    'authPluginId' => $this->id,
                    'publicKey'    => $publicKeyValue,
                    'privateKey'   => $encryptedPrivateKeyValue,
                    'MAC'          => $macValue
                )
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError(
                'DB error while registering a user for an auth plugin: ' . $Exception->getMessage()
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.auth.plugin.register.database.error'
            ));
        }
    }

    /**
     * Get derived key from authentication information
     *
     * @return Key
     */
    public function getDerivedKey()
    {
        return $this->AuthClass->getDerivedKey();
    }

    /**
     * Get list of User IDs of users that are registered with this plugin
     *
     * @return array
     */
    public function getRegisteredUserIds()
    {
        return $this->AuthClass->getRegisteredUserIds();
    }
}