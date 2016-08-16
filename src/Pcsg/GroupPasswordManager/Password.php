<?php

/**
 * This file contains \QUI\Kapitalschutz\Events
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Actors\CryptoGroup;
use Pcsg\GroupPasswordManager\Actors\CryptoUser;
use Pcsg\GroupPasswordManager\Constants\Permissions;
use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Authentication\SecurityClass;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\Keys\AuthKeyPair;
use Pcsg\GroupPasswordManager\Security\Keys\Key;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\SecretSharing;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use QUI;
use QUI\Permissions\Permission;

/**
 * Class Password
 *
 * Main class representing a password object and offering password specific methods
 *
 * @package pcsg/grouppasswordmanager
 * @author www.pcsg.de (Patrick Müller)
 */
class Password extends QUI\QDOM
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
     * Encrypted password data
     *
     * @var string
     */
    protected $cryptoDataEncrypted = null;

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
     * if omitted use session user
     * @throws QUI\Exception
     */
    public function __construct($id)
    {
        $id = (int)$id;


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
                    'passwordId' => $id
                )
            ), 404);
        }

        $this->User   = CryptoActors::getCryptoUser(); // session user
        $passwordData = current($result);

        // check integrity/authenticity of password data
        $passwordDataMAC      = $passwordData['MAC'];
        $passwordDataMACCheck = MAC::create(
            implode(
                '',
                array(
                    $passwordData['ownerId'],
                    $passwordData['ownerType'],
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

        $this->id = $passwordData['id'];

        // set public attributes
        $this->setAttributes(array(
            'title'       => $passwordData['title'],
            'description' => $passwordData['description'],
            'dataType'    => $passwordData['dataType'],
            'ownerId'     => $passwordData['ownerId'],
            'ownerType'   => $passwordData['ownerType']
        ));

        // set private attributes
        $this->cryptoDataEncrypted = $passwordData['cryptoData'];
        $this->SecurityClass       = Authentication::getSecurityClass($passwordData['securityClassId']);
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

        $this->decrypt();

        $viewData = array(
            'id'              => $this->id,
            'title'           => $this->getAttribute('title'),
            'description'     => $this->getAttribute('description'),
            'payload'         => $this->getSecretAttribute('payload'),
            'dataType'        => $this->getAttribute('dataType'),
            'securityClassId' => $this->SecurityClass->getId()
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

        $this->decrypt();

        $data = array(
            'id'              => $this->id,
            'title'           => $this->getAttribute('title'),
            'description'     => $this->getAttribute('description'),
            'payload'         => $this->getSecretAttribute('payload'),
            'ownerId'         => $this->getAttribute('ownerId'),
            'ownerType'       => $this->getAttribute('ownerType'),
            'dataType'        => $this->getAttribute('dataType'),
            'securityClassId' => $this->SecurityClass->getId()
        );

        return $data;
    }

    /**
     * Edit password data
     *
     * @param $passwordData
     *
     * @throws QUI\Exception
     */
    public function setData($passwordData)
    {
        if (!$this->hasPermission(self::PERMISSION_EDIT)) {
            $this->permissionDenied();
        }

        $this->decrypt();

        try {
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
                            $this->setAttribute('title', $v);
                        }
                        break;

                    case 'description':
                        if (is_string($v)) {
                            $this->setAttribute('description', $v);
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
                            $this->setAttribute('dataType', $v);
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
        } catch (\Exception $Exception) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.setdata.error',
                array(
                    'passwordId' => $this->id,
                    'error'      => $Exception->getMessage()
                )
            ));
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

        $this->decrypt();

        $data = array(
            'id'              => $this->id,
            'title'           => $this->getAttribute('title'),
            'description'     => $this->getAttribute('description'),
            'dataType'        => $this->getAttribute('dataType'),
            'sharedWith'      => $this->getSecretAttribute('sharedWith'),
            'securityClassId' => $this->SecurityClass->getId()
        );

        return $data;
    }

    /**
     * Set share users and groups
     *
     * @param array $shareData
     * @return void
     *
     * @throws QUI\Exception
     */
    public function setShareData($shareData)
    {
        if (!$this->hasPermission(self::PERMISSION_SHARE)) {
            $this->permissionDenied();
        }

        $this->decrypt();

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
                        $CryptoUser = CryptoActors::getCryptoUser($actorId);

                        // cannot share with owner
                        if ($this->isOwner($CryptoUser)) {
                            continue;
                        }

                        // create password access for user
                        $this->createUserPasswordAccess($CryptoUser);
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
                        if ($this->getAttribute('ownerType') === $this::OWNER_TYPE_GROUP
                            && $actorId === $this->getAttribute('ownerId')
                        ) {
                            continue;
                        }

                        $this->createGroupPasswordAccess($Group);

                        $newShareGroupIds[] = $Group->getId();
                    } catch (\Exception $Exception) {
                        QUI\System\Log::addError(
                            'Could not share with group #' . $shareActor['id'] . ': '
                            . $Exception->getMessage()
                        );

                        // @todo msg an user
                    }
                    break;
            }
        }

        $newShareUserIds  = array_unique($newShareUserIds);
        $newShareGroupIds = array_unique($newShareGroupIds);

        // delete access from old share users and groups
        $currentShareActors   = $this->getSecretAttribute('sharedWith');
        $currentShareUserIds  = $currentShareActors['users'];
        $currentShareGroupIds = $currentShareActors['groups'];

        $deleteShareUserIds = array_diff($currentShareUserIds, $newShareUserIds);

        foreach ($deleteShareUserIds as $id) {
            try {
                $this->removeUserPasswordAccess(CryptoActors::getCryptoUser($id));
            } catch (\Exception $Exception) {
                // @todo error log und meldung an user
            }
        }

        $deleteShareGroupIds = array_diff($currentShareGroupIds, $newShareGroupIds);

        foreach ($deleteShareGroupIds as $id) {
            try {
                $Group = CryptoActors::getCryptoGroup($id);
                $this->removeGroupPasswordAccess($Group);
            } catch (\Exception $Exception) {
                // @todo error log und meldung an user
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

        $cryptoDataEncrypted = SymmetricCrypto::encrypt(
            json_encode($cryptoData),
            $this->getPasswordKey()
        );

        $passwordData = array(
            'ownerId'         => $this->getAttribute('ownerId'),
            'ownerType'       => $this->getAttribute('ownerType'),
            'securityClassId' => $this->SecurityClass->getId(),
            'title'           => $this->getAttribute('title'),
            'description'     => $this->getAttribute('description'),
            'dataType'        => $this->getAttribute('dataType'),
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

            // first: delete access entries for users and groups
            $DB->delete(
                Tables::USER_TO_PASSWORDS,
                array(
                    'dataId' => $this->id
                )
            );

            $DB->delete(
                Tables::GROUP_TO_PASSWORDS,
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
        $currentOwnerId   = $this->getAttribute('ownerId');
        $currentOwnerType = $this->getAttribute('ownerType');

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
                    $this->createUserPasswordAccess($User);
                    $newOwnerId   = $User->getId();
                    $newOwnerType = self::OWNER_TYPE_USER;
                } catch (\Exception $Exception) {
                    QUI\System\Log::addError(
                        'Could not create access data for user #' . $User->getId() . ': '
                        . $Exception->getMessage()
                    );

                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.password.change.owner.user.error',
                        array(
                            'passwordId' => $this->id,
                            'newOwnerId' => $User->getId()
                        )
                    ));
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

                try {
                    $this->createGroupPasswordAccess($Group);
                    $newOwnerId   = $Group->getId();
                    $newOwnerType = self::OWNER_TYPE_GROUP;
                } catch (\Exception $Exception) {
                    QUI\System\Log::addError(
                        'Could not create access data for group #' . $Group->getId() . ': '
                        . $Exception->getMessage()
                    );

                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.password.change.owner.group.error',
                        array(
                            'passwordId' => $this->id,
                            'newOwnerId' => $Group->getId()
                        )
                    ));
                }

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
                $CryptoUser = CryptoActors::getCryptoUser($currentOwnerId);

                try {
                    $this->removeUserPasswordAccess($CryptoUser);
                } catch (\Exception $Exception) {
                    QUI\System\Log::addError(
                        'Could not delete access data for user #' . $CryptoUser->getId() . ': '
                        . $Exception->getMessage()
                    );

                    // @todo abbrechen
                }
                break;

            case self::OWNER_TYPE_GROUP:
                $CryptoGroup = CryptoActors::getCryptoGroup($currentOwnerId);

                try {
                    $this->removeGroupPasswordAccess($CryptoGroup);
                } catch (\Exception $Exception) {
                    QUI\System\Log::addError(
                        'Could not delete access data for group #' . $CryptoGroup->getId() . ': '
                        . $Exception->getMessage()
                    );

                    // @todo abbrechen
                }
        }

        return true;
    }

    /**
     * Create password access for user
     *
     * @param CryptoUser $User
     * @return void
     *
     * @throws QUI\Exception
     */
    public function createUserPasswordAccess($User)
    {
        if (!$this->hasPermission(self::PERMISSION_SHARE)) {
            $this->permissionDenied();
        }

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

        $this->decrypt();

        // split key
        $payloadKeyParts = SecretSharing::splitSecret(
            $this->getPasswordKey()->getValue(),
            $this->SecurityClass->getAuthPluginCount(),
            $this->SecurityClass->getRequiredFactors()
        );

        // encrypt key parts with user public keys
        $i  = 0;
        $DB = QUI::getDataBase();

        $userAuthKeyPairs = $User->getAuthKeyPairsBySecurityClass($this->SecurityClass);

        /** @var AuthKeyPair $UserAuthKeyPair */
        foreach ($userAuthKeyPairs as $UserAuthKeyPair) {
            $payloadKeyPart = $payloadKeyParts[$i++];

            $encryptedPayloadKeyPart = AsymmetricCrypto::encrypt(
                $payloadKeyPart,
                $UserAuthKeyPair
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
    public function createGroupPasswordAccess($Group)
    {
        if (!$this->hasPermission(self::PERMISSION_SHARE)) {
            $this->permissionDenied();
        }

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

        $this->decrypt();

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
     * @param CryptoUser $CryptoUser
     * @return true - on success
     *
     * @throws QUI\Exception
     */
    protected function removeUserPasswordAccess($CryptoUser)
    {
        // @todo experimental - check if possible
        if ($this->isOwner($CryptoUser)) {
            return false;
        }

        QUI::getDataBase()->delete(
            Tables::USER_TO_PASSWORDS,
            array(
                'userId' => $CryptoUser->getId(),
                'dataId' => $this->id,
            )
        );

        return true;
    }

    /**
     * Remove password access for a group
     *
     * @param CryptoGroup $CryptoGroup
     * @return true - on success
     *
     * @throws QUI\Exception
     */
    protected function removeGroupPasswordAccess($CryptoGroup)
    {
        // @todo experimental - check if possible
        if ($this->isOwner($CryptoGroup)) {
            return false;
        }

        QUI::getDataBase()->delete(
            Tables::GROUP_TO_PASSWORDS,
            array(
                'groupId' => $CryptoGroup->getId(),
                'dataId'  => $this->id,
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
    public function getPasswordKey()
    {
        if (!is_null($this->PasswordKey)) {
            return $this->PasswordKey;
        }

        $this->PasswordKey = $this->User->getPasswordAccessKey($this->id);

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
        $ownerType = $this->getAttribute('ownerType');

        switch ($permission) {
            case self::PERMISSION_VIEW:
                return $this->hasPasswordAccess($this->User);
                break;
            case self::PERMISSION_EDIT:
                return $this->isOwner($this->User);
                break;

            case self::PERMISSION_DELETE:
                if ($this->User->isSU()) {
                    return true;
                }

                if ($ownerType === self::OWNER_TYPE_USER) {
                    return $this->isOwner($this->User);
                }

                if (!QUI\Permissions\Permission::hasPermission(Permissions::PASSWORDS_DELETE)) {
                    return false;
                }

                return $this->isOwner($this->User);

            case self::PERMISSION_SHARE:
                if ($ownerType === self::OWNER_TYPE_USER) {
                    return $this->isOwner($this->User);
                }

                if (!QUI\Permissions\Permission::hasPermission(Permissions::PASSWORDS_SHARE)) {
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
        $ownerId   = (int)$this->getAttribute('ownerId');
        $ownerType = $this->getAttribute('ownerType');

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

        if ($CryptoActor instanceof CryptoGroup) {
            switch ($ownerType) {
                case self::OWNER_TYPE_USER:
                    return false;
                    break;

                case self::OWNER_TYPE_GROUP:
                    return $actorId === $ownerId;
                    break;
            }
        }

        return false;
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

    /**
     * Decrypt password sensitive data
     *
     * @throws QUI\Exception
     */
    protected function decrypt()
    {
        if (!$this->SecurityClass->isAuthenticated()) {
            // @todo eigenen 401 error code einfügen
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.user.not.authenticated',
                array(
                    'id'     => $this->id,
                    'userId' => $this->User->getId()
                )
            ));
        }

        $PasswordKey = $this->getPasswordKey();

        // decrypt password content
        $contentDecrypted = SymmetricCrypto::decrypt(
            $this->cryptoDataEncrypted,
            $PasswordKey
        );

        $contentDecrypted = json_decode($contentDecrypted, true);

        // check password content
        if (json_last_error() !== JSON_ERROR_NONE
            || !isset($contentDecrypted['payload'])
            || !isset($contentDecrypted['sharedWith'])
            || !isset($contentDecrypted['history'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.acces.data.decryption.fail',
                array(
                    'passwordId' => $this->id
                )
            ));
        }

        $this->setSecretAttributes(array(
            'payload'    => $contentDecrypted['payload'],
            'history'    => $contentDecrypted['history'],
            'sharedWith' => $contentDecrypted['sharedWith']
        ));
    }
}
