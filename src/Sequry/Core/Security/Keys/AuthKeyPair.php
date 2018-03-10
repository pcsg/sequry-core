<?php

namespace Sequry\Core\Security\Keys;

use Sequry\Core\Constants\Tables;
use Sequry\Core\Actors\CryptoUser;
use Sequry\Core\Security\Authentication\Plugin;
use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Security\HiddenString;
use Sequry\Core\Security\MAC;
use Sequry\Core\Security\SymmetricCrypto;
use Sequry\Core\Security\Utils;
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
            'from'  => Tables::keyPairsUser(),
            'where' => array(
                'id' => $id
            ),
            'limit' => 1
        ));

        if (empty($result)) {
            throw new QUI\Exception(array(
                'sequry/core',
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
            new HiddenString($publicKeyValue . $privateKeyValueEncrypted),
            Utils::getSystemKeyPairAuthKey()
        );

        // check integrity and authenticity of keypair
        if (!MAC::compare($keyPairMACCheck, $keyPairMAC)) {
            QUI\System\Log::addCritical(
                'Key Pair #' . $data['id'] . ' is possibly altered! MAC mismatch!'
            );

            throw new QUI\Exception(array(
                'sequry/core',
                'exception.authkeypair.not.authentic',
                array(
                    'keyPairId' => $id
                )
            ));
        }

        parent::__construct(
            new HiddenString($publicKeyValue),
            new HiddenString($privateKeyValueEncrypted)
        );

        $this->id         = $id;
        $this->User       = CryptoActors::getCryptoUser($data['userId']);
        $this->AuthPlugin = Authentication::getAuthPlugin($data['authPluginId']);
    }

    /**
     * Return ID of this key pair
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Return AuthPlugin of this KeyPair
     *
     * @return Plugin
     */
    public function getAuthPlugin()
    {
        return $this->AuthPlugin;
    }

    /**
     * Return decrypted private key
     *
     * @return Key
     */
    public function getPrivateKey()
    {
        $privateKeyEncrypted = $this->PrivateKey->getValue();
        $AuthPluginKey       = $this->AuthPlugin->getDerivedKey($this->User);

        $privateKey = SymmetricCrypto::decrypt(
            $privateKeyEncrypted,
            $AuthPluginKey
        );

        return new Key($privateKey);
    }
}
