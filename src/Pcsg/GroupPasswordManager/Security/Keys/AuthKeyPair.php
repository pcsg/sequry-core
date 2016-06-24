<?php

namespace Pcsg\GroupPasswordManager\Security\Keys;

use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\CryptoUser;
use Pcsg\GroupPasswordManager\Security\Authentication\Plugin;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use QUI;

/**
 * AuthKeyPair class
 *
 * Contains a key pair for a specific User and Authentication Plugin
 */
class AuthKeyPair extends KeyPair
{
    /**
     * ID of keypair
     *
     * @var integer
     */
    protected $id = null;

    /**
     * Owner of this key pair
     *
     * @var CryptoUser $User
     */
    protected $User = null;

    /**
     * Associated authentication plugin
     *
     * @var Plugin
     */
    protected $AuthPlugin = null;

    /**
     * AuthKeyPair constructor.
     *
     * @param integer $id - key pair id
     * @throws QUI\Exception
     */
    public function __construct($id)
    {
        $id = (int)$id;

        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::KEYPAIRS,
            'where' => array(
                'id' => $id
            ),
            'limit' => 1
        ));

        if (empty($result)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.authkeypair.not.found',
                array(
                    'id' => $id
                )
            ), 404);
        }

        $data                     = current($result);
        $publicKeyValue           = $data['publicKey'];
        $privateKeyValueEncrypted = $data['privateKey'];

        $keyPairMAC      = $data['MAC'];
        $keyPairMACCheck = MAC::create(
            $publicKeyValue . $privateKeyValueEncrypted,
            Utils::getSystemKeyPairAuthKey()
        );

        // check integrity and authenticity of keypair
        if (!Utils::compareStrings($keyPairMACCheck, $keyPairMAC)) {
            QUI\System\Log::addCritical(
                'Key Pair #' . $data['id'] . ' is possibly altered! MAC mismatch!'
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.authkeypair.not.authentic',
                array(
                    'keyPairId' => $id
                )
            ));
        }

        parent::__construct($publicKeyValue, $privateKeyValueEncrypted);

        $this->id         = $id;
        $this->User       = CryptoActors::getCryptoUser($data['userId']);
        $this->AuthPlugin = Authentication::getAuthPlugin($data['authPluginId']);
    }

    /**
     * Return ID of this key pair
     *
     * @return integer
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Return decrypted private key
     *
     * @return Key
     */
    public function getPrivateKey()
    {
        $privateKeyEncrypted = $this->PrivateKey->getValue();
        $AuthPluginKey       = $this->AuthPlugin->getDerivedKey();

        $privateKey = SymmetricCrypto::decrypt(
            $privateKeyEncrypted,
            $AuthPluginKey
        );

        return new Key($privateKey);
    }
}