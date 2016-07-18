<?php

/**
 * This file contains \QUI\Kapitalschutz\Events
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Actors\CryptoGroup;
use Pcsg\GroupPasswordManager\Actors\CryptoUser;
use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Authentication\Plugin;
use Pcsg\GroupPasswordManager\Security\Authentication\SecurityClass;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\Keys\AuthKeyPair;
use Pcsg\GroupPasswordManager\Security\Keys\Key;
use Pcsg\GroupPasswordManager\Security\Keys\KeyPair;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\SecretSharing;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use QUI;
use Symfony\Component\Console\Helper\Table;

/**
 * Class Password
 *
 * Main class representing a password object and offering password specific methods
 *
 * @package pcsg/grouppasswordmanager
 * @author www.pcsg.de (Patrick Müller)
 */
class Password
{
    /**
     * Permission constants
     */
    const PERMISSION_VIEW   = 1;
    const PERMISSION_EDIT   = 2;
    const PERMISSION_DELETE = 3;
    const PERMISSION_SHARE  = 4;

    const OWNER_TYPE_USER  = 1;
    const OWNER_TYPE_GROUP = 2;


    /**
     * Password ID
     *
     * @var integer
     */
    protected $id = null;

    /**
     * ID of password owner
     *
     * @var integer
     */
    protected $ownerId = null;

    /**
     * Owner type: "user" or "group"
     *
     * @var string
     */
    protected $ownerType = null;

    /**
     * Password payload (secret data)
     *
     * @var mixed
     */
    protected $payload = null;

    /**
     * Password history
     *
     * @var array
     */
    protected $history = null;

    /**
     * List of users/groups the password is shared with
     *
     * @var array
     */
    protected $sharedWith = null;

    /**
     * Password title
     *
     * @var string
     */
    protected $title = null;

    /**
     * Password description
     *
     * @var string
     */
    protected $description = null;

    /**
     * Password type
     *
     * @var string
     */
    protected $dataType = null;

    /**
     * Security Class of this password
     *
     * @var SecurityClass
     */
    protected $SecurityClass = null;

    /**
     * Password de/encryption key
     *
     * @var Key
     */
    protected $PasswordKey = null;

    /**
     * User that is currently handling this password
     *
     * @var CryptoUser
     */
    protected $User = null;

    /**
     * Attributes that are secret (and saved encrypted)
     *
     * @var array
     */
    protected $secretAttributes = array();

    /**
     * Password constructor.
     *
     * @param integer $id - Password ID
     * @param CryptoUser $CryptoUser (optional) - The user that wants to interact with this password;
     * if omitted use session user
     * @throws QUI\Exception
     */
    public function __construct($id, $CryptoUser = null)
    {
        ini_set('display_errors', 1);

        $id = (int)$id;

        if (is_null($CryptoUser)) {
            $CryptoUser = CryptoActors::getCryptoUser();
        }

        $this->User = $CryptoUser;

        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::PASSWORDS,
            'where' => array(
                'id' => $id
            )
        ));

        if (empty($result)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.not.found',
                array(
                    'id' => $id
                )
            ), 404);
        }

        $passwordData = current($result);

        // check integrity/authenticity of password data
        $passwordDataMAC      = $passwordData['MAC'];
        $passwordDataMACCheck = MAC::create(
            implode(
                '',
                array(
                    $passwordData['securityClassId'],
                    $passwordData['title'],
                    $passwordData['description'],
                    $passwordData['dataType'],
                    $passwordData['cryptoData']
                )
            ),
            Utils::getSystemPasswordAuthKey()
        );

        if (!MAC::compare($passwordDataMAC, $passwordDataMACCheck)) {
            QUI\System\Log::addCritical(
                'Password data #' . $id . ' is possibly altered! MAC mismatch!'
            );

            // @todo eigenen 401 error code
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.not.authentic',
                array(
                    'passwordId' => $id
                )
            ));
        }

        $this->id            = $passwordData['id'];
        $this->title         = $passwordData['title'];
        $this->description   = $passwordData['description'];
        $this->dataType      = $passwordData['dataType'];
        $this->SecurityClass = Authentication::getSecurityClass($passwordData['securityClassId']);

        if (!$this->SecurityClass->isAuthenticated()) {
            // @todo eigenen 401 error code einfügen
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.user.not.authenticated',
                array(
                    'id'     => $id,
                    'userId' => $CryptoUser->getId()
                )
            ));
        }

        $PasswordKey = $this->getPasswordKey();

        // decrypt password content
        $contentDecrypted = SymmetricCrypto::decrypt(
            $passwordData['cryptoData'],
            $PasswordKey
        );

        $contentDecrypted = json_decode($contentDecrypted, true);

        // check password content
        if (json_last_error() !== JSON_ERROR_NONE
            || !isset($contentDecrypted['ownerId'])
            || !isset($contentDecrypted['ownerType'])
            || !isset($contentDecrypted['payload'])
            || !isset($contentDecrypted['sharedWith'])
            || !isset($contentDecrypted['history'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.acces.data.decryption.fail',
                array(
                    'passwordId' => $id
                )
            ));
        }

        $this->setSecretAttributes(array(
            'ownerId'    => $contentDecrypted['ownerId'],
            'ownerType'  => $contentDecrypted['ownerType'],
            'payload'    => $contentDecrypted['payload'],
            'history'    => $contentDecrypted['history'],
            'sharedWith' => $contentDecrypted['sharedWith']
        ));
    }

    /**
     * Returns password data for frontend view (contains payload!)
     *
     * @return array
     */
    public function getViewData()
    {
        if (!$this->hasPermission(self::PERMISSION_VIEW)) {
            $this->permissionDenied();
        }

        $viewData = array(
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'payload'     => $this->getSecretAttribute('payload'),
            'dataType'    => $this->dataType
        );

        return $viewData;
    }

    /**
     * Get all data except share data (contains payload!)
     *
     * @return array
     */
    public function getData()
    {
        if (!$this->hasPermission(self::PERMISSION_EDIT)) {
            $this->permissionDenied();
        }

        $data = array(
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'payload'     => $this->getSecretAttribute('payload'),
            'ownerId'     => $this->getSecretAttribute('ownerId'),
            'ownerType'   => $this->getSecretAttribute('ownerType'),
            'dataType'    => $this->dataType
        );

        return $data;
    }

    /**
     * Edit password data
     *
     * @param $passwordData
     */
    public function setData($passwordData)
    {
        foreach ($passwordData as $k => $v) {
            switch ($k) {
                // security class
                case 'securityClassId':
                    // @todo re-encrypt for every owner and access user with new security class
//                    $sanitizedData['securityClassId'] = $this->SecurityClass->getId();
                    break;

                case 'title':
                    if (is_string($v)
                        && !empty($v)
                    ) {
                        $this->title = $v;
                    }
                    break;

                case 'description':
                    if (is_string($v)) {
                        $this->description = $v;
                    }
                    break;

                case 'payload':
                    if (!empty($v)) {
                        $oldPayload = $this->getSecretAttribute('payload');

                        if ($oldPayload == $v) {
                            continue;
                        }

                        $this->setSecretAttribute('payload', $v);

                        // write history entry if payload changes
                        $history = $this->getSecretAttribute('history');

                        $history[] = array(
                            'timestamp' => time(),
                            'value'     => $oldPayload
                        );

                        $this->setSecretAttribute('history', $history);
                    }
                    break;

                case 'dataType':
                    if (!empty($v)
                        && is_string($v)
                    ) {
                        $this->dataType = $v;
                    }
                    break;

                case 'owner':
                    if (is_array($v)
                        && isset($v['id'])
                        && !empty($v['id'])
                        && is_numeric($v['id'])
                        && isset($v['type'])
                        && !empty($v['type'])
                    ) {
                        $newOwnerId   = (int)$v['id'];
                        $newOwnerType = $v['type'];

                        $this->changeOwner($newOwnerId, $newOwnerType);
                    }
                    break;
            }
        }

        $this->save();
    }

    /**
     * Get data of groups and users this password is shared with
     *
     * @return array
     */
    public function getShareData()
    {
        if (!$this->hasPermission(self::PERMISSION_SHARE)) {
            $this->permissionDenied();
        }

        $data = array(
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'dataType'    => $this->dataType,
            'sharedWith'  => $this->getSecretAttribute('sharedWith')
        );

        return $data;
    }

    /**
     * Set share users and groups
     *
     * @param array $shareData
     */
    public function setShareData($shareData)
    {
        $newShareUserIds  = array();
        $newShareGroupIds = array();

        foreach ($shareData as $shareActor) {
            if (!isset($shareActor['type'])
                || empty($shareActor['type'])
                || !isset($shareActor['id'])
                || empty($shareActor['id'])
            ) {
                continue;
            }

            $actorId = (int)$shareActor['id'];

            switch ($shareActor['type']) {
                case self::OWNER_TYPE_USER:
                    try {
                        $User = QUI::getUsers()->get($actorId);

                        // cannot share with owner
                        if ($this->isOwner($User)) {
                            continue;
                        }

                        // create password access for user
                        $CryptoUser = CryptoActors::getCryptoUser($actorId);
                        $this->createAccessForUser($CryptoUser);
                        $newShareUserIds[] = $CryptoUser->getId();
                    } catch (\Exception $Exception) {
                        QUI\System\Log::addError(
                            'Could not share with user #' . $shareActor['id'] . ': '
                            . $Exception->getMessage()
                        );

                        // @todo msg an user
                    }
                    break;

                case self::OWNER_TYPE_GROUP:
                    try {
                        $Group = CryptoActors::getCryptoGroup($actorId);

                        // cannot share with owner group
                        if ($this->getSecretAttribute('ownerType') === $this::OWNER_TYPE_GROUP
                            && $actorId === $this->getSecretAttribute('ownerId')
                        ) {
                            continue;
                        }

                        $this->createAccessForGroup($Group);

                        $newShareGroupIds[] = $Group->getId();
                    } catch (\Exception $Exception) {
                        QUI\System\Log::addError(
                            'Could not share with group #' . $shareActor['id'] . ': '
                            . $Exception->getMessage()
                        );

                        // @todo msg an user
                    }
                    break;

                default:
            }
        }

        // delete access from old share users and groups
        $currentShareActors   = $this->getSecretAttribute('sharedWith');
        $currentShareUserIds  = $currentShareActors['users'];
        $currentShareGroupIds = $currentShareActors['groups'];

        $deleteShareUserIds = array_diff($currentShareUserIds, $newShareUserIds);

        foreach ($deleteShareUserIds as $id) {
            try {
                $this->removePasswordAccess(QUI::getUsers()->get($id));
            } catch (\Exception $Exception) {
                // @todo error log und meldung an user
            }
        }

        $deleteShareGroupIds = array_diff($currentShareGroupIds, $newShareGroupIds);

        foreach ($deleteShareGroupIds as $id) {
            $Group = QUI::getGroups()->get($id);
            $users = $Group->getUsers();

            foreach ($users as $row) {
                $User = QUI::getUsers()->get($row['id']);

                // cannot unshare with owner
//                if ($this->isOwner($User)) {
//                    continue;
//                }

                $this->removePasswordAccess($User, $Group);
            }
        }

        $this->setSecretAttribute(
            'sharedWith',
            array(
                'users'  => $newShareUserIds,
                'groups' => $newShareGroupIds
            )
        );

        $this->save();
    }

    /**
     * Save data to password object
     *
     * @return true - on success
     */
    protected function save()
    {
        $cryptoData = $this->getSecretAttributes();

        \QUI\System\Log::writeRecursive($cryptoData);

        $cryptoDataEncrypted = SymmetricCrypto::encrypt(
            json_encode($cryptoData),
            $this->getPasswordKey()
        );

        $passwordData = array(
            'securityClassId' => $this->SecurityClass->getId(),
            'title'           => $this->title,
            'description'     => $this->description,
            'dataType'        => $this->dataType,
            'cryptoData'      => $cryptoDataEncrypted
        );

        // calculate new MAC
        $newMAC = MAC::create(
            implode('', $passwordData),
            Utils::getSystemPasswordAuthKey()
        );

        $passwordData['MAC'] = $newMAC;

        // update database entry
        $DB = QUI::getDataBase();

        try {
            $DB->update(
                Tables::PASSWORDS,
                $passwordData,
                array(
                    'id' => $this->id
                )
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Could not write password data to db: ' . $Exception->getMessage()
            );

            // @todo abbrechen
        }

        return true;
    }

    /**
     * Delete password irrevocably
     *
     * @return void
     * @throws QUI\Exception
     */
    public function delete()
    {
        if (!$this->hasPermission(self::PERMISSION_DELETE)) {
            $this->permissionDenied();
        }

        try {
            $DB = QUI::getDataBase();

            // first: delete access entries
            $DB->delete(
                Tables::USER_TO_PASSWORDS,
                array(
                    'dataId' => $this->id
                )
            );

            // second: delete password entry
            $DB->delete(
                Tables::PASSWORDS,
                array(
                    'id' => $this->id
                )
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Password #' . $this->id . ' delete error: ' . $Exception->getMessage()
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.delete.error',
                array(
                    'passwordId' => $this->id
                )
            ));
        }
    }

    /**
     * Changes password owner to user or group
     *
     * @param integer $id - user or group id
     * @param integer $type - OWNER_TYPE_USER or OWNER_TYPE_GROUP
     *
     * @return true - on success
     *
     * @throws QUI\Exception
     */
    protected function changeOwner($id, $type)
    {
        $currentOwnerId   = $this->getSecretAttribute('ownerId');
        $currentOwnerType = $this->getSecretAttribute('ownerType');

        // set access data for new owner(s)
        switch ($type) {
            case self::OWNER_TYPE_USER:
            case 'user':
                if ($currentOwnerType === self::OWNER_TYPE_GROUP) {
                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.password.change.owner.group.to.user'
                    ));
                }

                if ((int)$currentOwnerId === (int)$id) {
                    return true;
                }

                $User = CryptoActors::getCryptoUser($id);

                try {
                    $this->createPasswordAccess($User);
                    $newOwnerId   = $User->getId();
                    $newOwnerType = self::OWNER_TYPE_USER;
                } catch (\Exception $Exception) {
                    QUI\System\Log::addError(
                        'Could not create access data for user #' . $User->getId() . ': '
                        . $Exception->getMessage()
                    );

                    // @todo abbrechen
                }
                break;

            case self::OWNER_TYPE_GROUP:
            case 'group':
                if ((int)$currentOwnerId === (int)$id
                    && $currentOwnerType === self::OWNER_TYPE_GROUP
                ) {
                    return true;
                }

                $Group = CryptoActors::getCryptoGroup($id);
                $users = $Group->getUsers();

                foreach ($users as $row) {
                    $User = CryptoActors::getCryptoUser($row['id']);

                    try {
                        $this->createPasswordAccess($User, $Group);
                    } catch (\Exception $Exception) {
                        QUI\System\Log::addError(
                            'Could not create access data for user #' . $User->getId() . ': '
                            . $Exception->getMessage()
                        );

                        // @todo abbrechen
                    }
                }

                $newOwnerId   = $Group->getId();
                $newOwnerType = self::OWNER_TYPE_GROUP;
                break;

            default:
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.password.change.owner.wrong.type'
                ));
        }

        $this->setSecretAttributes(array(
            'ownerId'   => $newOwnerId,
            'ownerType' => $newOwnerType
        ));

        // delete access data for old owner(s)
        switch ($currentOwnerType) {
            case self::OWNER_TYPE_USER:
                $User = QUI::getUsers()->get($currentOwnerId);

                try {
                    $this->removePasswordAccess($User);
                } catch (\Exception $Exception) {
                    QUI\System\Log::addError(
                        'Could not delete access data for user #' . $User->getId() . ': '
                        . $Exception->getMessage()
                    );

                    // @todo abbrechen
                }
                break;

            case self::OWNER_TYPE_GROUP:
                $Group = QUI::getGroups()->get($currentOwnerId);
                $users = $Group->getUsers();

                foreach ($users as $row) {
                    $User = CryptoActors::getCryptoUser($row['id']);

                    try {
                        $this->removePasswordAccess($User);
                    } catch (\Exception $Exception) {
                        QUI\System\Log::addError(
                            'Could not delete access data for user #' . $User->getId() . ': '
                            . $Exception->getMessage()
                        );

                        // @todo abbrechen
                    }
                }
        }

        return true;
    }

    /**
     * Creates and inserts password access entry for a user
     *
     * @param CryptoUser $User
     * @param CryptoGroup $Group (optional) - create access via group
     *
     * @return true - on success
     *
     * @throws QUI\Exception
     */
    public function createPasswordAccess($User, $Group = null)
    {

    }

    /**
     * Create password access for user
     *
     * @param CryptoUser $User
     * @return void
     *
     * @throws QUI\Exception
     */
    public function createAccessForUser($User)
    {
        // skip if user already has password access
        if ($this->hasPasswordAccess($User)) {
            return;
        }

        if (!$this->SecurityClass->isUserEligible($User)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.create.access.user.not.eligible',
                array(
                    'userId'             => $User->getId(),
                    'userName'           => $User->getUsername(),
                    'securityClassId'    => $this->SecurityClass->getId(),
                    'securityClassTitle' => $this->SecurityClass->getAttribute('title')
                )
            ));
        }

        // split key
        $authPlugins     = $this->SecurityClass->getAuthPlugins();
        $userAuthPlugins = $User->getAuthPluginsBySecurityClass($this->SecurityClass);

        $payloadKeyParts = SecretSharing::splitSecret(
            $this->getPasswordKey()->getValue(),
            count($authPlugins),
            $this->SecurityClass->getRequiredFactors()
        );

        // encrypt key parts with user public keys
        $i  = 0;
        $DB = QUI::getDataBase();

        /** @var Plugin $AuthPlugin */
        foreach ($userAuthPlugins as $AuthPlugin) {
            $UserAuthKeyPair = $User->getAuthKeyPair($AuthPlugin);
            $payloadKeyPart  = $payloadKeyParts[$i++];

            $encryptedPayloadKeyPart = AsymmetricCrypto::encrypt(
                $payloadKeyPart, $UserAuthKeyPair
            );

            $dataAccessEntry = array(
                'userId'    => $User->getId(),
                'dataId'    => $this->id,
                'dataKey'   => $encryptedPayloadKeyPart,
                'keyPairId' => $UserAuthKeyPair->getId()
            );

            $dataAccessEntry['MAC'] = MAC::create(
                implode('', $dataAccessEntry),
                Utils::getSystemKeyPairAuthKey()
            );

            $DB->insert(
                Tables::USER_TO_PASSWORDS,
                $dataAccessEntry
            );
        }
    }

    /**
     * Create password access for group
     *
     * @param CryptoGroup $Group
     * @return void
     *
     * @throws QUI\Exception
     */
    public function createAccessForGroup($Group)
    {
        // skip if group already has password access
        if ($this->hasPasswordAccess($Group)) {
            return;
        }

        if (!$this->SecurityClass->isGroupEligible($Group)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.create.access.group.not.eligible',
                array(
                    'groupId'            => $Group->getId(),
                    'groupName'          => $Group->getAttribute('name'),
                    'securityClassId'    => $this->SecurityClass->getId(),
                    'securityClassTitle' => $this->SecurityClass->getAttribute('title')
                )
            ));
        }

        $GroupKeyPair = $Group->getKeyPair();

        // encrypt password payload key with group public key
        $passwordKeyEncrypted = AsymmetricCrypto::encrypt(
            $this->getPasswordKey()->getValue(),
            $GroupKeyPair
        );

        $dataAccessEntry = array(
            'groupId' => $Group->getId(),
            'dataId'  => $this->id,
            'dataKey' => $passwordKeyEncrypted
        );

        $dataAccessEntry['MAC'] = MAC::create(
            implode('', $dataAccessEntry),
            Utils::getSystemKeyPairAuthKey()
        );

        QUI::getDataBase()->insert(
            Tables::GROUP_TO_PASSWORDS,
            $dataAccessEntry
        );
    }

    /**
     * Checks if a user has access to this password
     *
     * @param CryptoUser|CryptoGroup $CryptoActor
     * @return bool
     */
    protected function hasPasswordAccess($CryptoActor)
    {
        if ($CryptoActor instanceof CryptoUser) {
            $result = QUI::getDataBase()->fetch(array(
                'count' => 1,
                'from'  => Tables::USER_TO_PASSWORDS,
                'where' => array(
                    'userId' => $CryptoActor->getId(),
                    'dataId' => $this->id,
                )
            ));
        } else {
            $result = QUI::getDataBase()->fetch(array(
                'count' => 1,
                'from'  => Tables::GROUP_TO_PASSWORDS,
                'where' => array(
                    'groupId' => $CryptoActor->getId(),
                    'dataId'  => $this->id,
                )
            ));
        }

        return current(current($result)) > 0;
    }

    /**
     * Remove password access for a user
     *
     * @param QUI\Users\User $User
     * @param QUI\Groups\Group $Group (optional) - remove access via group
     * @return true - on success
     *
     * @throws QUI\Exception
     */
    public function removePasswordAccess($User, $Group = null)
    {
        // @todo experimental - check if possible
        if ($this->isOwner($User)) {
            return false;
        }

        QUI::getDataBase()->delete(
            Tables::USER_TO_PASSWORDS,
            array(
                'userId'  => $User->getId(),
                'dataId'  => $this->id,
                'groupId' => is_null($Group) ? null : $Group->getId()
            )
        );

        return true;
    }

    /**
     * Get IDs of groups that have access to this password
     */
    protected function getAccessGroupsIds()
    {
        $groupIds = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'groupId'
            ),
            'from'   => Tables::GROUP_TO_PASSWORDS,
            'where'  => array(
                'dataId' => $this->id
            )
        ));

        foreach ($result as $row) {
            $groupIds[] = $row['groupId'];
        }

        return $groupIds;
    }

    /**
     * Get password de/encryption key
     *
     * @return Key
     * @throws QUI\Exception
     */
    protected function getPasswordKey()
    {
        if (!is_null($this->PasswordKey)) {
            return $this->PasswordKey;
        }

        // try to get access data via user
        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::USER_TO_PASSWORDS,
            'where' => array(
                'userId' => $this->User->getId(),
                'dataId' => $this->id
            )
        ));

        // if not found, try to get access data via group
        if (empty($result)) {
            $accessGroupIds = $this->getAccessGroupsIds();

            $result = QUI::getDataBase()->fetch(array(
                'from'  => Tables::USER_TO_GROUPS,
                'where' => array(
                    'groupId' => array(
                        'type'  => 'IN',
                        'value' => $accessGroupIds
                    ),
                    'userId'  => $this->User->getId()
                )
            ));

            if (empty($result)) {
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.password.access.data.not.found',
                    array(
                        'id'     => $this->id,
                        'userId' => $this->User->getId()
                    )
                ), 404);
            }

            // get group key
            $groupData = array();

            foreach ($result as $row) {
                $groupId = $row['groupId'];

                if (!isset($groupData[$groupId])) {
                    $groupData[$groupId] = array();
                }

                $groupData[$groupId][] = $row;
            }

            // select first group
            $groupKeyData  = array_shift($groupData);
            $groupId       = $groupKeyData[0]['groupId'];
            $groupKeyParts = array();

            // @todo integritätsprüfung
            foreach ($groupKeyData as $groupKeyInfo) {
                $AuthKeyPair     = Authentication::getAuthKeyPair($groupKeyInfo['userKeyPairId']);
                $groupKeyParts[] = AsymmetricCrypto::decrypt(
                    $groupKeyInfo['groupKey'],
                    $AuthKeyPair
                );
            }

            $GroupKeyDecryptionKey = new Key(SecretSharing::recoverSecret($groupKeyParts));

            // decrypt group key
            $CryptoGroup  = CryptoActors::getCryptoGroup($groupId);
            $GroupKeyPair = $CryptoGroup->getKeyPair();

            $GroupKeyDecrypted = new Key(
                SymmetricCrypto::decrypt(
                    $GroupKeyPair->getPrivateKey()->getValue(),
                    $GroupKeyDecryptionKey
                )
            );

            $GroupKeyPairDecrypted = new KeyPair(
                $GroupKeyPair->getPublicKey()->getValue(),
                $GroupKeyDecrypted->getValue()
            );

            // decrypt password key with group private key
            // @todo integrität prüfen
            $result = QUI::getDataBase()->fetch(array(
                'from'  => Tables::GROUP_TO_PASSWORDS,
                'where' => array(
                    'dataId'  => $this->id,
                    'groupId' => $groupId
                )
            ));

            $data = current($result);

            $passwordKeyDecryptedValue = AsymmetricCrypto::decrypt(
                $data['dataKey'],
                $GroupKeyPairDecrypted
            );

            $this->PasswordKey = new Key($passwordKeyDecryptedValue);

            return $this->PasswordKey;
        }

        $passwordKeyParts = array();

        foreach ($result as $row) {
            // check access data integrity/authenticity
            $accessDataMAC      = $row['MAC'];
            $accessDataMACCheck = MAC::create(
                implode(
                    '',
                    array(
                        $row['userId'],
                        $row['dataId'],
                        $row['dataKey'],
                        $row['keyPairId']
                    )
                ),
                Utils::getSystemKeyPairAuthKey()
            );

            if (!MAC::compare($accessDataMAC, $accessDataMACCheck)) {
                QUI\System\Log::addCritical(
                    'Password access data (uid #' . $row['userId'] . ', dataId #' . $row['dataId']
                    . ', keyPairId #' . $row['keyPairId'] . ' is possibly altered! MAC mismatch!'
                );

                // @todo eigenen 401 error code
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.password.acces.data.not.authentic',
                    array(
                        'passwordId' => $this->id
                    )
                ));
            }

            $AuthKeyPair        = Authentication::getAuthKeyPair($row['keyPairId']);
            $passwordKeyParts[] = AsymmetricCrypto::decrypt(
                $row['dataKey'],
                $AuthKeyPair
            );
        }

        // build password key from its parts
        $this->PasswordKey = new Key(
            SecretSharing::recoverSecret($passwordKeyParts)
        );

        return $this->PasswordKey;
    }

    /**
     * Checks if the current password user has a password specific permission
     *
     * @param integer $permission
     * @return bool
     */
    protected function hasPermission($permission)
    {
        $shareActors = $this->getSecretAttribute('sharedWith');
        $ownerType   = $this->getSecretAttribute('ownerType');

        switch ($permission) {
            case self::PERMISSION_VIEW:
                if ($this->isOwner($this->User)) {
                    return true;
                }

                $uId = $this->User->getId();

                if (in_array($uId, $shareActors['users'])) {
                    return true;
                }

                $groupIds = $shareActors['groups'];

                foreach ($groupIds as $groupId) {
                    if ($this->User->isInGroup($groupId)) {
                        return true;
                    }
                }

                return false;
                break;
            case self::PERMISSION_EDIT:
                return $this->isOwner($this->User);
                break;

            case self::PERMISSION_DELETE:
                if ($ownerType === self::OWNER_TYPE_USER) {
                    return $this->isOwner($this->User);
                }

                try {
                    QUI\Permissions\Permission::hasPermission(
                        'pcsg.gpm.cryptodata.delete'
                    );
                } catch (QUI\Exception $Exception) {
                    return false;
                }

                return $this->isOwner($this->User);

            case self::PERMISSION_SHARE:
                if ($ownerType === self::OWNER_TYPE_USER) {
                    return $this->isOwner($this->User);
                }

                try {
                    QUI\Permissions\Permission::hasPermission(
                        'pcsg.gpm.cryptodata.share'
                    );
                } catch (QUI\Exception $Exception) {
                    return false;
                }

                return $this->isOwner($this->User);
                break;

            default:
                return false;
        }
    }

    /**
     * Checks if a user or group is owner of this password
     *
     * @param CryptoUser|CryptoGroup $CryptoActor
     *
     * @return bool
     */
    protected function isOwner($CryptoActor)
    {
        $actorId   = (int)$CryptoActor->getId();
        $ownerId   = (int)$this->getSecretAttribute('ownerId');
        $ownerType = $this->getSecretAttribute('ownerType');

        if ($CryptoActor instanceof CryptoUser) {
            switch ($ownerType) {
                case self::OWNER_TYPE_USER:
                    return $actorId === $ownerId;
                    break;

                case self::OWNER_TYPE_GROUP:
                    return $CryptoActor->isInGroup($ownerId);
                    break;
            }
        }

        switch ($ownerType) {
            case self::OWNER_TYPE_USER:
                return false;
                break;

            case self::OWNER_TYPE_GROUP:
                return $actorId === $ownerId;
                break;
        }
    }

    /**
     * Throws permission denied exception
     *
     * @throws QUI\Exception
     */
    protected function permissionDenied()
    {
        // @todo eigenen 401 fehlercode einfügen
        throw new QUI\Exception(array(
            'pcsg/grouppasswordmanager',
            'exception.password.permission.denied'
        ));
    }

    /**
     * Sets a secret attribute - secret attributes are attributes that are to be encrypted
     *
     * @param string $k
     * @param mixed $v
     */
    protected function setSecretAttribute($k, $v)
    {
        $this->secretAttributes[$k] = $v;
    }

    /**
     * Sets secret attributes - secret attributes are attributes that are to be encrypted
     *
     * @param array $attributes
     */
    protected function setSecretAttributes($attributes)
    {
        foreach ($attributes as $k => $v) {
            $this->setSecretAttribute($k, $v);
        }
    }

    /**
     * Returns a secret attribute - secret attributes are attributes that are to be encrypted
     *
     * @param string $k
     * @return mixed
     */
    protected function getSecretAttribute($k)
    {
        if (!isset($this->secretAttributes[$k])) {
            return false;
        }

        return $this->secretAttributes[$k];
    }

    /**
     * Returns all secret attributes - secret attributes are attributes that are to be encrypted
     *
     * @return array
     */
    protected function getSecretAttributes()
    {
        return $this->secretAttributes;
    }
}