<?php

namespace Pcsg\GroupPasswordManager\Security\Authentication;

use ParagonIE\Halite\Contract\SymmetricKeyCryptoInterface;
use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
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
            throw new QUI\Exception(
                'Authentication plugin #' . $id . ' not found.',
                404
            );
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
     * @return \QUI\Control
     */
    public function getAuthenticationControl()
    {
        return $this->AuthClass->getAuthenticationControl();
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
            $this->AuthClass->register($information);

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
        $publicKeyValue           = $KeyPair->getPublicKeyValue();
        $encryptedPrivateKeyValue = SymmetricCrypto::encrypt(
            $KeyPair->getPrivateKeyValue(),
            $AuthKey
        );

        // calculate MAC with system auth key
        // @todo noch mehr informationen in den MAC einfließen lassen (plugin id, user id etc.)
        $macValue = MAC::create(
            $publicKeyValue . $encryptedPrivateKeyValue,
            Utils::getSystemAuthKey()
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