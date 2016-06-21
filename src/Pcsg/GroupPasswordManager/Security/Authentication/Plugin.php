<?php

namespace Pcsg\GroupPasswordManager\Security\Authentication;

use ParagonIE\Halite\Contract\SymmetricKeyCryptoInterface;
use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Interfaces\iAuthPlugin;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
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
     * Registers the current session user with this Plugin
     *
     * @param mixed $information - authentication information
     * @throws QUI\Exception
     */
    public function registerUser($information)
    {
        // authenticate user to check if information given is correct
        $this->AuthClass->register($information);
        $this->AuthClass->authenticate($information);

        // if authentication information is correct, create new keypair for user
        $KeyPair = AsymmetricCrypto::generateKeyPair();

        // derive key from authentication information
        $Key = SymmetricCrypto::generateKey();

        // encrypt private key
        $publicKeyValue           = $KeyPair->getPublicKeyValue();
        $encryptedPrivateKeyValue = SymmetricCrypto::encrypt(
            $KeyPair->getPrivateKeyValue(),
            $Key
        );


    }
}