<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Actors\CryptoGroup
 */

namespace Pcsg\GroupPasswordManager\Actors;

use ParagonIE\Halite\Symmetric\Crypto;
use Pcsg\GroupPasswordManager\Security\Authentication\SecurityClass;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\Keys\Key;
use Pcsg\GroupPasswordManager\Security\Keys\KeyPair;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\Utils;
use QUI;
use Pcsg\GroupPasswordManager\Constants\Tables;
use Symfony\Component\Console\Helper\Table;

/**
 * Group Class
 *
 * Represents a password manager Group that can retrieve encrypted passwords
 * if the necessary permission are given.
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class CryptoGroup extends QUI\Groups\Group
{
    /**
     * Group key pair
     *
     * @var KeyPair
     */
    protected $KeyPair = null;

    /**
     * SecurityClass this groups is associated to
     *
     * @var SecurityClass
     */
    protected $SecurityClass = null;

    /**
     * CryptoGroup constructor.
     *
     * @param integer $groupId - quiqqer group id
     */
    public function __construct($groupId)
    {
        parent::__construct($groupId);

        $this->KeyPair = $this->getKeyPair();
    }

    /**
     * Return Key pair for specific authentication plugin
     *
     * @return KeyPair
     * @throws QUI\Exception
     */
    public function getKeyPair()
    {
        if (!is_null($this->KeyPair)) {
            return $this->KeyPair;
        }

        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::KEYPAIRS_GROUP,
            'where' => array(
                'groupId' => $this->getId()
            ),
            'limit' => 1
        ));

        if (empty($result)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptogroup.keypair.not.found',
                array(
                    'groupId' => $this->getId()
                )
            ));
        }

        $data = current($result);

        // check keypair integrity
        $integrityData = array(
            $data['groupId'],
            $data['securityClassId'],
            $data['publicKey'],
            $data['privateKey']
        );

        $MACExpected = $data['MAC'];
        $MACActual   = MAC::create(implode('', $integrityData), Utils::getSystemKeyPairAuthKey());

        if (!MAC::compare($MACActual, $MACExpected)) {
            QUI\System\Log::addCritical(
                'Group key pair #' . $data['id'] . ' possibly altered. MAC mismatch!'
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.cryptogroup.keypair.not.authentic',
                array(
                    'groupId' => $this->getId()
                )
            ));
        }

        $this->KeyPair = new KeyPair($data['publicKey'], $data['privateKey']);

        return $this->KeyPair;
    }

    /**
     * Return SecurityClass that is associated with this group
     *
     * @return SecurityClass
     */
    public function getSecurityClass()
    {
        if (!is_null($this->SecurityClass)) {
            return $this->SecurityClass;
        }

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'securityClassId'
            ),
            'from'   => Tables::KEYPAIRS_GROUP,
            'where'  => array(
                'id' => $this->getId()
            )
        ));

        $this->SecurityClass = Authentication::getSecurityClass($result[0]['id']);

        return $this->SecurityClass;
    }

    /**
     * Adds user to this group so he can access all passwords the group has access to
     *
     * @param CryptoUser $CryptoUser
     */
    public function addCryptoUser($CryptoUser)
    {
        // split key
        $authPlugins = $this->SecurityClass->getAuthPlugins();
        $GroupKey    = $this->getPasswordKey();

        $payloadKeyParts = SecretSharing::splitSecret(
            $PasswordKey->getValue(),
            count($authPlugins)
        );

        // encrypt key parts with user public keys
        $i = 0;

        /** @var Plugin $Plugin */
        foreach ($authPlugins as $Plugin) {
            try {
                $UserAuthKeyPair = $User->getAuthKeyPair($Plugin);
                $payloadKeyPart  = $payloadKeyParts[$i++];

                $encryptedPayloadKeyPart = AsymmetricCrypto::encrypt(
                    $payloadKeyPart, $UserAuthKeyPair
                );

                $dataAccessEntry = array(
                    'userId'    => $User->getId(),
                    'dataId'    => $this->id,
                    'dataKey'   => $encryptedPayloadKeyPart,
                    'keyPairId' => $UserAuthKeyPair->getId(),
                    'groupId'   => is_null($Group) ? null : $Group->getId(),
                );

                $dataAccessEntry['MAC'] = MAC::create(
                    implode('', $dataAccessEntry),
                    Utils::getSystemKeyPairAuthKey()
                );

                $DB->insert(
                    Tables::USER_TO_PASSWORDS,
                    $dataAccessEntry
                );
            } catch (\Exception $Exception) {
                QUI\System\Log::addError(
                    'Error writing password key parts to database: ' . $Exception->getMessage()
                );

                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.password.create.access.data.general.error'
                ));
            }
        }

        return true;
    }

    /**
     * Remove access to group for crypto user
     *
     * @param CryptoUser $CryptoUser
     * @return void
     */
    public function removeCryptoUser($CryptoUser)
    {
        if (!$this->hasCryptoUserAccess($CryptoUser)) {
            return;
        }

        QUI::getDataBase()->delete(
            Tables::USER_TO_GROUPS,
            array(
                'userId'  => $CryptoUser->getId(),
                'groupId' => $this->getId()
            )
        );
    }

    /**
     * Return IDs of all user that have access to this CryptoGroup
     *
     * @return array
     */
    public function getCryptoUserIds()
    {
        $userIds = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'userId'
            ),
            'from'   => Tables::USER_TO_GROUPS,
            'where'  => array(
                'groupId' => $this->getId()
            )
        ));

        foreach ($result as $row) {
            $userIds[] = $row['userId'];
        }

        return $userIds;
    }

    /**
     * Checks if a user has access to this group
     *
     * @param CryptoUser $User (optional) - if omitted use session user
     *
     * @return bool
     */
    public function hasCryptoUserAccess($User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        $userIds = $this->getCryptoUserIds();

        return in_array($User->getId(), $userIds);
    }

    /**
     * Decrypt group private key with current session user
     *
     * @return Key - (decrypted) private key
     */
    protected function decryptGroupPrivateKey()
    {
        $CryptoUser = CryptoActors::getCryptoUser();


    }
}