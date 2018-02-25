<?php

namespace Sequry\Core\Security\Authentication;

use Sequry\Core\Actors\CryptoUser;
use Sequry\Core\Constants\Tables;
use Sequry\Core\Security\AsymmetricCrypto;
use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Security\Interfaces\IAuthPlugin;
use Sequry\Core\Security\Keys\Key;
use Sequry\Core\Security\MAC;
use Sequry\Core\Security\SymmetricCrypto;
use Sequry\Core\Security\Utils;
use Sequry\Core\Security\HiddenString;
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
     * @var IAuthPlugin
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
            'from'  => Tables::authPlugins(),
            'where' => array(
                'id' => (int)$id
            )
        ));

        if (empty($result)) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.auth.plugin.not.found',
                array(
                    'id' => $id
                )
            ), 404);
        }

        $data = current($result);

        $this->AuthClass = new $data['path']();
        $this->id        = $data['id'];

        $L = QUI::getLocale();
        $t = json_decode($data['title'], true);
        $d = json_decode($data['description'], true);

        $this->setAttributes(array(
            'title'       => $L->get($t[0], $t[1]),
            'description' => $L->get($d[0], $d[1])
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
     * @param HiddenString $information (optional) - authentication information
     * @param QUI\Users\User $User (optional) - if omitted use session user
     * @return true - if authenticated
     * @throws QUI\Exception
     */
    public function authenticate(HiddenString $information, $User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        return $this->AuthClass->authenticate($information, $User);
    }

    /**
     * Checks if current session user is authenticated with this plugin
     *
     * @param QUI\Users\User $User (optional) - if omitted use session user
     * @return bool
     */
    public function isAuthenticated($User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        if (Authentication::existsAuthKeyInSession($this->id)) {
            return true;
        }

        return $this->AuthClass->isAuthenticated($User);
    }

    /**
     * Change authentication information
     *
     * @param mixed $old - current authentication information
     * @param mixed $new - new authentication information
     * @param \QUI\Users\User $User (optional) - if omitted, use current session user
     * @return void
     *
     * @throws QUI\Exception
     */
    public function changeAuthenticationInformation(HiddenString $old, HiddenString $new, $User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        Authentication::clearAuthDataFromSession();

        $this->authenticate($old, $User);

        $AuthKeyPair     = CryptoActors::getCryptoUser()->getAuthKeyPair($this);
        $publicKeyValue  = $AuthKeyPair->getPublicKey()->getValue();
        $privateKeyValue = $AuthKeyPair->getPrivateKey()->getValue();

        $this->AuthClass->changeAuthenticationInformation($old, $new, $User);

        // encrypt auth private key with derived key from new authentication information
        $encryptedPrivateKeyValue = SymmetricCrypto::encrypt(
            $privateKeyValue,
            $this->getDerivedKey($User)
        );

        // calculate new MAC
        // @todo noch mehr informationen in den MAC einfließen lassen (plugin id, user id etc.)
        $macValue = MAC::create(
            new HiddenString(
                $publicKeyValue->getString()
                . $encryptedPrivateKeyValue
            ),
            Utils::getSystemKeyPairAuthKey()
        );

        try {
            // put everything in the database
            QUI::getDataBase()->update(
                Tables::keyPairsUser(),
                array(
                    'publicKey'  => $publicKeyValue,
                    'privateKey' => $encryptedPrivateKeyValue,
                    'MAC'        => $macValue
                ),
                array(
                    'userId'       => $User->getId(),
                    'authPluginId' => $this->id
                )
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError(
                'DB error while changing auth info for a user for an auth plugin: ' . $Exception->getMessage()
            );

            throw new QUI\Exception(array(
                'sequry/core',
                'exception.auth.plugin.changeauth.database.error'
            ));
        }
    }

    /**
     * Registers a user with this Plugin
     *
     * @param \QUI\Users\User $User (optional) - if omitted, use current session user
     * @param HiddenString $information (optional) - registration information
     * @return mixed - authentication information that can decrypt the private key
     * @throws QUI\Exception
     */
    public function registerUser(HiddenString $information, $User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        try {
            // register with plugin
            $authInformation = $this->AuthClass->register($information, $User);

            // authenticate with plugin
            $this->authenticate($authInformation, $User);

            // get derived key from authentication information
            $AuthKey = $this->getDerivedKey($User);
        } catch (QUI\Database\Exception $Exception) {
            QUI\System\Log::addError(
                'DB error while registering a user for an auth plugin: ' . $Exception->getMessage()
            );

            throw new QUI\Exception(array(
                'sequry/core',
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
            new HiddenString(
                $publicKeyValue->getString()
                . $encryptedPrivateKeyValue
            ),
            Utils::getSystemKeyPairAuthKey()
        );

        try {
            // put everything in the database
            QUI::getDataBase()->insert(
                Tables::keyPairsUser(),
                array(
                    'userId'       => $User->getId(),
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
                'sequry/core',
                'exception.auth.plugin.register.database.error'
            ));
        }

        return $authInformation;
    }

    /**
     * Checks if the user is registered with this plugin
     *
     * @param QUI\Users\User $User
     * @return bool
     */
    public function isRegistered($User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        return $this->AuthClass->isRegistered($User);
    }

    /**
     * Get derived key from authentication information
     *
     * @param \QUI\Users\User $User (optional) - if omitted, use current session user
     * @return Key
     *
     * @throws QUI\Exception
     */
    public function getDerivedKey($User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        $Key = Authentication::getAuthKey($this->id);

        if ($Key) {
            return $Key;
        }

        try {
            $DerivedKey = $this->AuthClass->getDerivedKey($User);
            return $DerivedKey;
        } catch (\Exception $Exception) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.auth.plugin.derived.key.error',
                array(
                    'pluginId' => $this->id,
                    'error'    => $Exception->getMessage()
                )
            ));
        }
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

    /**
     * Deletes user from this plugin
     *
     * @param CryptoUser $CryptoUser
     * @return void
     *
     * @throws QUI\Exception
     */
    public function deleteUser($CryptoUser)
    {
        $SessionUser = QUI::getUserBySession();

        if ((int)$SessionUser->getId() !== $CryptoUser->getId()
            && !$SessionUser->isSU()
        ) {
            throw new QUI\Exception(array(
                'sequry/core',
                'exception.auth.plugin.delete.user.no.permission',
            ));
        }

        $this->AuthClass->deleteUser($CryptoUser);
    }
}
