<?php

/**
 * This file contains \Sequry\Core\Password
 */

namespace Sequry\Core;

use Sequry\Core\Exception\PermissionDeniedException;
use Sequry\Core\Security\HiddenString;
use QUI\Cache\Manager as CacheManager;
use Sequry\Core\Actors\CryptoGroup;
use Sequry\Core\Actors\CryptoUser;
use Sequry\Core\Constants\Permissions;
use Sequry\Core\Constants\Tables;
use Sequry\Core\Security\AsymmetricCrypto;
use Sequry\Core\Security\Authentication\SecurityClass;
use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Security\Keys\AuthKeyPair;
use Sequry\Core\Security\Keys\Key;
use Sequry\Core\Security\MAC;
use Sequry\Core\Security\SecretSharing;
use Sequry\Core\Security\SymmetricCrypto;
use Sequry\Core\Security\Utils;
use QUI;
use QUI\Permissions\Permission;
use Sequry\Core\Handler\Categories;

/**
 * Class Password
 *
 * Main class representing a password object and offering password specific methods
 *
 * @package sequry/core
 * @author www.pcsg.de (Patrick Müller)
 */
class Password extends QUI\QDOM
{
    /**
     * Permission constants
     */
    const PERMISSION_VIEW        = 1;
    const PERMISSION_EDIT        = 2;
    const PERMISSION_DELETE      = 3;
    const PERMISSION_SHARE       = 4;
    const PERMISSION_SHARE_GROUP = 5;

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
    protected $secretAttributes = [];

    /**
     * Flag if password content has already been decrypted
     *
     * @var bool
     */
    protected $decrypted = false;

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

        $result = QUI::getDataBase()->fetch([
            'from'  => Tables::passwords(),
            'where' => [
                'id' => $id
            ]
        ]);

        if (empty($result)) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.password.not.found',
                [
                    'passwordId' => $id
                ]
            ], 404);
        }

        $passwordData = current($result);

        // check integrity/authenticity of password data
        $passwordDataMAC = $passwordData['MAC'];

        // (re)calculate MAC from actual data
        $macFields = SymmetricCrypto::decrypt(
            $passwordData['MACFields'],
            Utils::getSystemPasswordAuthKey()
        );

        $macFields = json_decode($macFields, true);
        $macData   = [];

        foreach ($macFields as $field) {
            if (isset($passwordData[$field])
                && !empty($passwordData[$field])
            ) {
                $macData[] = $passwordData[$field];
            } else {
                $macData[] = null;
            }
        }

        $passwordDataMACActual = MAC::create(
            new HiddenString(implode('', $macData)),
            Utils::getSystemPasswordAuthKey()
        );

        if (!MAC::compare($passwordDataMAC, $passwordDataMACActual)) {
            QUI\System\Log::addCritical(
                'Password data #' . $id . ' is possibly altered! MAC mismatch!'
            );

            // @todo eigenen 401 error code
            throw new QUI\Exception([
                'sequry/core',
                'exception.password.not.authentic',
                [
                    'passwordId' => $id
                ]
            ]);
        }

        $this->id = $passwordData['id'];

        // set public attributes
        $this->setAttributes([
            'title'        => $passwordData['title'],
            'description'  => $passwordData['description'],
            'dataType'     => $passwordData['dataType'],
            'ownerId'      => $passwordData['ownerId'],
            'ownerType'    => $passwordData['ownerType'],
            'viewCount'    => $passwordData['viewCount'],
            'createUserId' => $passwordData['createUserId'],
            'createDate'   => $passwordData['createDate'],
            'editUserId'   => $passwordData['editUserId'],
            'editDate'     => $passwordData['editDate']
        ]);

        // set categories
        if (!empty($passwordData['categoryIds'])) {
            $this->setAttribute('categoryIds', explode(',', trim($passwordData['categoryIds'], ',')));
        }

        if (!empty($passwordData['categories'])) {
            $this->setAttribute('categories', explode(',', trim($passwordData['categories'], ',')));
        }

        // ownerId and ownerTye are additionally saved as secret attributes
        // because they may not be altered via public "setAttribute()"-method
        $this->setSecretAttributes([
            'ownerId'   => (int)$passwordData['ownerId'],
            'ownerType' => (int)$passwordData['ownerType']
        ]);

        // set private attributes
        $this->cryptoDataEncrypted = $passwordData['cryptoData'];
        $this->SecurityClass       = Authentication::getSecurityClass($passwordData['securityClassId']);
    }

    /**
     * Get Password ID
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns password data for frontend view (contains payload!)
     *
     * @return array
     * @throws \Sequry\Core\Exception\PermissionDeniedException
     */
    public function getViewData()
    {
        if (!$this->decrypted
            && !$this->hasPermission(self::PERMISSION_VIEW)
        ) {
            $this->permissionDenied();
        }

        $this->decrypt();

        $viewData = [
            'id'              => $this->id,
            'title'           => $this->getAttribute('title'),
            'description'     => $this->getAttribute('description'),
            'payload'         => $this->getSecretAttribute('payload'),
            'dataType'        => $this->getAttribute('dataType'),
            'securityClassId' => $this->SecurityClass->getId(),
            'categoryIds'     => $this->getAttribute('categoryIds'),
            'createUserId'    => $this->getAttribute('createUserId'),
            'createDate'      => $this->getAttribute('createDate'),
            'editUserId'      => $this->getAttribute('editUserId'),
            'editDate'        => $this->getAttribute('editDate')
        ];

        // private category ids
        if (!is_null($this->User)) {
            $metaData                       = $this->User->getPasswordMetaData($this->id);
            $viewData['categoryIdsPrivate'] = explode(',', trim($metaData['categoryIds'], ','));
            $viewData['favorite']           = $metaData['favorite'];
        }

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

        $data = [
            'id'              => $this->id,
            'title'           => $this->getAttribute('title'),
            'description'     => $this->getAttribute('description'),
            'payload'         => $this->getSecretAttribute('payload'),
            'ownerId'         => $this->getAttribute('ownerId'),
            'ownerType'       => $this->getAttribute('ownerType'),
            'dataType'        => $this->getAttribute('dataType'),
            'securityClassId' => $this->SecurityClass->getId(),
            'categoryIds'     => $this->getAttribute('categoryIds')
        ];

        // private category ids
        $metaData                   = $this->User->getPasswordMetaData($this->id);
        $data['categoryIdsPrivate'] = explode(',', trim($metaData['categoryIds'], ','));

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

        // handle some attributes with priority
        $SecurityClass = false;

        if (!empty($passwordData['securityClassId'])
            && (int)$passwordData['securityClassId'] !== $this->getSecurityClass()->getId()
        ) {
            $SecurityClass       = Authentication::getSecurityClass((int)$passwordData['securityClassId']);
            $this->SecurityClass = $SecurityClass;

            QUI::getEvents()->fireEvent('passwordSecurityClassChange', [$this, $SecurityClass]);
        }

        foreach ($passwordData as $k => $v) {
            switch ($k) {
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

                        $history[] = [
                            'timestamp' => time(),
                            'value'     => $oldPayload
                        ];

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
                        && !empty($v['id'])
                        && is_numeric($v['id'])
                        && !empty($v['type'])
                    ) {
                        $newOwnerId   = (int)$v['id'];
                        $newOwnerType = $v['type'];

                        $this->changeOwner($newOwnerId, $newOwnerType);
                    }
                    break;

                case 'categoryIds':
                    CacheManager::clear('sequry/core/publiccategoryaccess/');

                    if (empty($v)) {
                        $this->setAttribute('categories', null);
                        $this->setAttribute('categoryIds', null);

                        break;
                    }

                    if (!is_array($v)) {
                        break;
                    }

                    $family = [];

                    foreach ($v as $catId) {
                        $family = array_merge(
                            $family,
                            Categories::getPublicCategoryFamilyList((int)$catId)
                        );
                    }

                    if (!empty($family)) {
                        $this->setAttribute('categories', array_unique($family));
                    }

                    $this->setAttribute('categoryIds', $v);
                    break;
            }
        }

        if ($SecurityClass) {
            $this->setSecurityClass($SecurityClass);
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

        $data = [
            'id'              => $this->id,
            'title'           => $this->getAttribute('title'),
            'description'     => $this->getAttribute('description'),
            'dataType'        => $this->getAttribute('dataType'),
            'securityClassId' => $this->SecurityClass->getId(),
            'ownerUserIds'    => $this->getOwnerUserIds(),
            'ownerGroupIds'   => []
        ];

        $currentOwnerId   = (int)$this->getSecretAttribute('ownerId');
        $currentOwnerType = (int)$this->getSecretAttribute('ownerType');

        if ($currentOwnerType === self::OWNER_TYPE_GROUP) {
            $data['ownerGroupIds'][] = $currentOwnerId;
        }

        foreach ($data['ownerUserIds'] as $k => $v) {
            $data['ownerUserIds'][$k] = 'u' . $v;
        }

        foreach ($data['ownerGroupIds'] as $k => $v) {
            $data['ownerGroupIds'][$k] = 'g' . $v;
        }

        // check if share users / groups stil exist
        $Users       = QUI::getUsers();
        $Groups      = QUI::getGroups();
        $shareChange = false;

        $sharedWith = $this->getSecretAttribute('sharedWith');

        foreach ($sharedWith['users'] as $k => $userId) {
            try {
                $Users->get($userId);
            } catch (\Exception $Exception) {
                if ($Exception->getCode() === 404) {
                    QUI\System\Log::addNotice(
                        'User #' . $userId . ' was removed from password #' . $this->getId()
                        . ' because user could not be found.'
                    );

                    QUI::getMessagesHandler()->addAttention(
                        QUI::getLocale()->get(
                            'sequry/core',
                            'message.password.share_user_not_found',
                            [
                                'userId' => $userId
                            ]
                        )
                    );
                } else {
                    QUI\System\Log::addWarning(
                        'User #' . $userId . ' was removed from password #' . $this->getId()
                        . ' because an error occurred when the user was loaded: '
                        . $Exception->getMessage()
                    );

                    QUI::getMessagesHandler()->addAttention(
                        QUI::getLocale()->get(
                            'sequry/core',
                            'message.password.share_user_error',
                            [
                                'userId' => $userId
                            ]
                        )
                    );
                }

                unset($sharedWith['users'][$k]);
                $shareChange = true;
            }
        }

        foreach ($sharedWith['groups'] as $k => $groupId) {
            try {
                $Groups->get($groupId);
            } catch (\Exception $Exception) {
                if ($Exception->getCode() === 404) {
                    QUI\System\Log::addNotice(
                        'Group #' . $groupId . ' was removed from password #' . $this->getId()
                        . ' because group could not be found.'
                    );

                    QUI::getMessagesHandler()->addAttention(
                        QUI::getLocale()->get(
                            'sequry/core',
                            'message.password.share_group_not_found',
                            [
                                'groupId' => $groupId
                            ]
                        )
                    );
                } else {
                    QUI\System\Log::addWarning(
                        'Group #' . $groupId . ' was removed from password #' . $this->getId()
                        . ' because an error occurred when the group was loaded: '
                        . $Exception->getMessage()
                    );

                    QUI::getMessagesHandler()->addAttention(
                        QUI::getLocale()->get(
                            'sequry/core',
                            'message.password.share_group_error',
                            [
                                'groupId' => $groupId
                            ]
                        )
                    );
                }

                unset($sharedWith['groups'][$k]);
                $shareChange = true;
            }
        }

        if ($shareChange) {
            $this->setSecretAttribute('sharedWith', $sharedWith);
            $this->save();
        }

        $data['sharedWith'] = $sharedWith;

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
        $OwnerActor = $this->getOwner();
        $permission = $OwnerActor instanceof CryptoUser ? self::PERMISSION_SHARE : self::PERMISSION_SHARE_GROUP;

        if (!$this->hasPermission($permission)) {
            $this->permissionDenied();
        }

        $this->decrypt();

        $newShareUserIds  = [];
        $newShareGroupIds = [];

        foreach ($shareData as $shareActor) {
            if (empty($shareActor['type'])
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
            [
                'users'  => $newShareUserIds,
                'groups' => $newShareGroupIds
            ]
        );

        $this->save();
    }

    /**
     * Get info about password access
     *
     * - Owner
     * - How is the password accessed (which groups)
     *
     * @return array
     * @throws QUI\Exception
     */
    public function getAccessInfo()
    {
        if (!$this->hasPermission(self::PERMISSION_VIEW)) {
            $this->permissionDenied();
        }

        $accessGroups = [];

        foreach ($this->getAccessGroupsIds() as $groupId) {
            $accessGroups[] = [
                'id'   => $groupId,
                'name' => QUI::getGroups()->get($groupId)->getName()
            ];
        }

        $ownerId   = $this->getAttribute('ownerId');
        $ownerType = '';
        $name      = '';

        switch ($this->getAttribute('ownerType')) {
            case $this::OWNER_TYPE_USER:
                $ownerType = 'user';
                $name      = CryptoActors::getCryptoUser($ownerId)->getName();
                break;

            case $this::OWNER_TYPE_GROUP:
                $ownerType = 'group';
                $name      = CryptoActors::getCryptoGroup($ownerId)->getName();
                break;
        }

        return [
            'owner'       => [
                'id'   => $ownerId,
                'name' => $name,
                'type' => $ownerType
            ],
            'access'      => [
                'user'   => in_array($this->getUser()->getId(), $this->getDirectAccessUserIds()),
                'groups' => $accessGroups
            ],
            'userIsOwner' => $ownerId == $this->getUser()->getId()
        ];
    }

    /**
     * Save data to password object
     *
     * @return true - on success
     */
    protected function save()
    {
        // categories
        $categories      = $this->getAttribute('categories');
        $categoriesEntry = null;

        if (!empty($categories)) {
            $categoriesEntry = ',' . implode(',', $categories) . ',';
        }

        $assignedCategoryIds     = $this->getAttribute('categoryIds');
        $categoriesAssignedEntry = null;

        if (!empty($assignedCategoryIds)) {
            $categoriesAssignedEntry = ',' . implode(',', $assignedCategoryIds) . ',';
        }

        // owner
        $ownerId = $this->getSecretAttribute('ownerId');

        if ($this->getSecretAttribute('newOwnerId')) {
            $ownerId = $this->getSecretAttribute('newOwnerId');
            $this->setSecretAttribute('ownerId', $ownerId);
            $this->setAttribute('ownerId', $ownerId);
        }

        $ownerType = $this->getSecretAttribute('ownerType');

        if ($this->getSecretAttribute('newOwnerType')) {
            $ownerType = $this->getSecretAttribute('newOwnerType');
            $this->setSecretAttribute('ownerType', $ownerType);
            $this->setAttribute('ownerType', $ownerType);
        }

        // encrypt secret password data
        $cryptoDataEncrypted = SymmetricCrypto::encrypt(
            new HiddenString(json_encode($this->getSecretAttributes())),
            $this->getPasswordKey()
        );

        $passwordData = [
            'ownerId'         => $ownerId,
            'ownerType'       => $ownerType,
            'securityClassId' => $this->SecurityClass->getId(),
            'title'           => $this->getAttribute('title'),
            'description'     => $this->getAttribute('description'),
            'dataType'        => $this->getAttribute('dataType'),
            'cryptoData'      => $cryptoDataEncrypted,
            'categories'      => $categoriesEntry,
            'categoryIds'     => $categoriesAssignedEntry,
            'editDate'        => time(),
            'editUserId'      => $this->User->getId()
        ];

        // encrypt fields used for MAC creation (MACFields)
        $macFields = SymmetricCrypto::encrypt(
            new HiddenString(json_encode(array_keys($passwordData))),
            Utils::getSystemPasswordAuthKey()
        );

        // calculate new MAC
        $newMAC = MAC::create(
            new HiddenString(implode('', $passwordData)),
            Utils::getSystemPasswordAuthKey()
        );

        $passwordData['MAC']       = $newMAC;
        $passwordData['MACFields'] = $macFields;

        // update database entry
        $DB = QUI::getDataBase();

        try {
            $DB->update(
                Tables::passwords(),
                $passwordData,
                [
                    'id' => $this->id
                ]
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

        // try to decrypt password to force authentication
        $this->decrypt();

        try {
            $DB = QUI::getDataBase();

            // first: delete access entries for users and groups
            $DB->delete(
                Tables::usersToPasswords(),
                [
                    'dataId' => $this->id
                ]
            );

            $DB->delete(
                Tables::groupsToPasswords(),
                [
                    'dataId' => $this->id
                ]
            );

            // second: delete password entry
            $DB->delete(
                Tables::passwords(),
                [
                    'id' => $this->id
                ]
            );

            // delete meta data entries
            $DB->delete(
                Tables::usersToPasswordMeta(),
                [
                    'dataId' => $this->id
                ]
            );

            QUI::getEvents()->fireEvent(
                'passwordDelete',
                [$this]
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Password #' . $this->id . ' delete error: ' . $Exception->getMessage()
            );

            throw new QUI\Exception([
                'sequry/core',
                'exception.password.delete.error',
                [
                    'passwordId' => $this->id
                ]
            ]);
        }
    }

    /**
     * Get data type of password payload (ftp, website, etc..)
     *
     * @return string
     */
    public function getDataType()
    {
        return $this->getAttribute('dataType');
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
        if (!$this->isOwner($this->getUser())) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.password.change.owner.no.permission'
            ]);
        }

        $id               = (int)$id;
        $currentOwnerId   = (int)$this->getSecretAttribute('ownerId');
        $currentOwnerType = (int)$this->getSecretAttribute('ownerType');

        $checkGroupSharePermission = $currentOwnerType === self::OWNER_TYPE_GROUP;

        // check owner change pre-requisites
        switch ($type) {
            case self::OWNER_TYPE_USER:
            case 'user':
                if ($currentOwnerType === self::OWNER_TYPE_GROUP) {
                    throw new QUI\Exception([
                        'sequry/core',
                        'exception.password.change.owner.group.to.user'
                    ]);
                }

                $NewOwner = CryptoActors::getCryptoUser($id);

                if (!$this->SecurityClass->isUserEligible($NewOwner)) {
                    throw new QUI\Exception([
                        'sequry/core',
                        'exception.password.create.access.user.not.eligible',
                        [
                            'userId'             => $NewOwner->getId(),
                            'userName'           => $NewOwner->getName(),
                            'securityClassId'    => $this->SecurityClass->getId(),
                            'securityClassTitle' => $this->SecurityClass->getAttribute('title')
                        ]
                    ]);
                }

                if ($currentOwnerId === $id) {
                    return true;
                }

                if ($checkGroupSharePermission
                    && !$this->hasPermission(self::PERMISSION_SHARE_GROUP)
                ) {
                    throw new QUI\Exception([
                        'sequry/core',
                        'exception.password.change.owner.no.group.share.permission'
                    ]);
                }
                break;

            case self::OWNER_TYPE_GROUP:
            case 'group':
                $NewOwner = CryptoActors::getCryptoGroup($id);

                if (!$this->SecurityClass->isGroupEligible($NewOwner)) {
                    throw new QUI\Exception([
                        'sequry/core',
                        'exception.password.create.access.group.not.eligible',
                        [
                            'groupId'            => $NewOwner->getId(),
                            'groupName'          => $NewOwner->getAttribute('name'),
                            'securityClassId'    => $this->SecurityClass->getId(),
                            'securityClassTitle' => $this->SecurityClass->getAttribute('title')
                        ]
                    ]);
                }

                if ($currentOwnerId === $id
                    && $currentOwnerType === self::OWNER_TYPE_GROUP
                ) {
                    return true;
                }

                if ($checkGroupSharePermission
                    && !$this->hasPermission(self::PERMISSION_SHARE_GROUP)
                ) {
                    throw new QUI\Exception([
                        'sequry/core',
                        'exception.password.change.owner.no.group.share.permission'
                    ]);
                }
                break;

            default:
                throw new QUI\Exception([
                    'sequry/core',
                    'exception.password.change.owner.wrong.type'
                ]);
        }

        if (!$this->hasPermission(self::PERMISSION_SHARE)) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.password.no.share.permission'
            ]);
        }

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

        // set access data for new owner(s)
        switch ($type) {
            case self::OWNER_TYPE_USER:
            case 'user':
                $User         = CryptoActors::getCryptoUser($id);
                $newOwnerId   = $User->getId();
                $newOwnerType = self::OWNER_TYPE_USER;

                // remove user from shared users
                $sharedWith = $this->getSecretAttribute('sharedWith');
                $k          = array_search($newOwnerId, $sharedWith['users']);

                if ($k !== false) {
                    unset($sharedWith['users'][$k]);
                    $this->setSecretAttribute('sharedWith', $sharedWith);
                }

                $this->createUserPasswordAccess($User);
                break;

            case self::OWNER_TYPE_GROUP:
            case 'group':
                $Group        = CryptoActors::getCryptoGroup($id);
                $newOwnerId   = $Group->getId();
                $newOwnerType = self::OWNER_TYPE_GROUP;

                // remove group from share groups
                $sharedWith = $this->getSecretAttribute('sharedWith');
                $k          = array_search($newOwnerId, $sharedWith['groups']);

                if ($k !== false) {
                    unset($sharedWith['groups'][$k]);
                    $this->setSecretAttribute('sharedWith', $sharedWith);

                    // remove group access first, so it can bet given again
                    // for all current users
                    $this->removeGroupPasswordAccess($Group);
                }

                $this->createGroupPasswordAccess($Group);
                break;

            default:
                throw new QUI\Exception([
                    'sequry/core',
                    'exception.password.change.owner.wrong.type'
                ]);
        }

        // set new owner
        $this->setSecretAttributes([
            'newOwnerId'   => $newOwnerId,
            'newOwnerType' => $newOwnerType
        ]);

        QUI::getEvents()->fireEvent('passwordOwnerChange', [$this, $NewOwner]);

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
            throw new QUI\Exception([
                'sequry/core',
                'exception.password.no.share.permission'
            ]);
        }

        // skip if user already has password access
        if ($this->hasPasswordAccess($User)) {
            return;
        }

        if (!$this->SecurityClass->isUserEligible($User)) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.password.create.access.user.not.eligible',
                [
                    'userId'             => $User->getId(),
                    'userName'           => $User->getName(),
                    'securityClassId'    => $this->SecurityClass->getId(),
                    'securityClassTitle' => $this->SecurityClass->getAttribute('title')
                ]
            ]);
        }

        $this->decrypt();
        $this->createMetaTableEntry($User);

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
                new HiddenString($payloadKeyPart),
                $UserAuthKeyPair
            );

            $dataAccessEntry = [
                'userId'    => $User->getId(),
                'dataId'    => $this->id,
                'dataKey'   => $encryptedPayloadKeyPart,
                'keyPairId' => $UserAuthKeyPair->getId()
            ];

            $dataAccessEntry['MAC'] = MAC::create(
                new HiddenString(implode('', $dataAccessEntry)),
                Utils::getSystemKeyPairAuthKey()
            );

            $DB->insert(
                Tables::usersToPasswords(),
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
            throw new QUI\Exception([
                'sequry/core',
                'exception.password.no.share.permission'
            ]);
        }

        // skip if group already has password access
        if ($this->hasPasswordAccess($Group)) {
            return;
        }

        if (!$this->SecurityClass->isGroupEligible($Group)) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.password.create.access.group.not.eligible',
                [
                    'groupId'            => $Group->getId(),
                    'groupName'          => $Group->getAttribute('name'),
                    'securityClassId'    => $this->SecurityClass->getId(),
                    'securityClassTitle' => $this->SecurityClass->getAttribute('title')
                ]
            ]);
        }

        $this->decrypt();

        // create meta table entries
        /** @var CryptoUser $CryptoUser */
        foreach ($Group->getCryptoUsers() as $CryptoUser) {
            $this->createMetaTableEntry($CryptoUser);
        }

        $GroupKeyPair = $Group->getKeyPair($this->SecurityClass);

        // encrypt password payload key with group public key
        $passwordKeyEncrypted = AsymmetricCrypto::encrypt(
            $this->getPasswordKey()->getValue(),
            $GroupKeyPair
        );

        $dataAccessEntry = [
            'groupId' => $Group->getId(),
            'dataId'  => $this->id,
            'dataKey' => $passwordKeyEncrypted
        ];

        $dataAccessEntry['MAC'] = MAC::create(
            new HiddenString(implode('', $dataAccessEntry)),
            Utils::getSystemKeyPairAuthKey()
        );

        QUI::getDataBase()->insert(
            Tables::groupsToPasswords(),
            $dataAccessEntry
        );
    }

    /**
     * Sets security class of this password
     *
     * @param SecurityClass $SecurityClass
     * @return void
     *
     * @throws QUI\Exception
     */
    public function setSecurityClass(SecurityClass $SecurityClass)
    {
        if (!$this->hasPermission(self::PERMISSION_EDIT)) {
            $this->permissionDenied();
        }

//        if ($this->SecurityClass->getId() == $SecurityClass->getId()) {
//            return;
//        }

        $ownerId = $this->getSecretAttribute('ownerId');

        switch ($this->getSecretAttribute('ownerType')) {
            case self::OWNER_TYPE_USER:
                $CryptoUser = CryptoActors::getCryptoUser($ownerId);

                if (!$SecurityClass->isUserEligible($CryptoUser)) {
                    throw new QUI\Exception([
                        'sequry/core',
                        'exception.password.setsecurityclass.owner.user.not.eligible',
                        [
                            'securityClassId'    => $SecurityClass->getId(),
                            'securityClassTitle' => $SecurityClass->getAttribute('title')
                        ]
                    ]);
                }
                break;

            case self::OWNER_TYPE_GROUP:
                $CryptoGroup = CryptoActors::getCryptoGroup($ownerId);

                if (!$SecurityClass->isGroupEligible($CryptoGroup)) {
                    throw new QUI\Exception([
                        'sequry/core',
                        'exception.password.setsecurityclass.owner.group.not.eligible',
                        [
                            'securityClassId'    => $SecurityClass->getId(),
                            'securityClassTitle' => $SecurityClass->getAttribute('title')
                        ]
                    ]);
                }
                break;
        }

        $this->SecurityClass = $SecurityClass;

        // re-encrypt password key for all users
        $userIds = $this->getDirectAccessUserIds();

        foreach ($userIds as $userId) {
            $CryptoUser = CryptoActors::getCryptoUser($userId);

            // if user is not eligible for security class -> delete password access
            if (!$SecurityClass->isUserEligible($CryptoUser)) {
                $this->removeUserPasswordAccess($CryptoUser);
                continue;
            }

            try {
                $CryptoUser->reEncryptPasswordAccessKey($this->id);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addError(
                    'Password :: setSecurityClass() -> could not set password access key for user #'
                    . $CryptoUser->getId() . ': ' . $Exception->getMessage()
                );
            }
        }

        // re-encrypt password key for all groups
        $groupIds = $this->getAccessGroupsIds();

        foreach ($groupIds as $groupId) {
            $CryptoGroup = CryptoActors::getCryptoGroup($groupId);

            // if user is not eligible for security class -> delete password access
            if (!$SecurityClass->isGroupEligible($CryptoGroup)) {
                $this->removeGroupPasswordAccess($CryptoGroup);
                continue;
            }

            try {
                $CryptoGroup->reEncryptPasswordAccessKey($this->id);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addError(
                    'Password :: setSecurityClass() -> could not set password access key for group #'
                    . $CryptoGroup->getId() . ': ' . $Exception->getMessage()
                );
            }
        }
    }

    /**
     * Get current SecurityClass of password
     *
     * @return SecurityClass
     */
    public function getSecurityClass()
    {
        return $this->SecurityClass;
    }

    /**
     * Checks if a user has access to this password
     *
     * @param CryptoUser|CryptoGroup $CryptoActor
     * @return bool
     */
    public function hasPasswordAccess($CryptoActor)
    {
        if ($CryptoActor instanceof CryptoUser) {
            if (in_array($CryptoActor->getId(), $this->getDirectAccessUserIds())) {
                return true;
            }

            return $this->hasPasswordAccessViaGroup($CryptoActor);
        }

        if ($CryptoActor instanceof CryptoGroup) {
            return in_array($CryptoActor->getId(), $this->getAccessGroupsIds());
        }

        return false;
    }

    /**
     * Determines if a user has access to this password via a password group
     *
     * @param CryptoUser $CryptoUser
     * @return bool
     */
    public function hasPasswordAccessViaGroup(CryptoUser $CryptoUser)
    {
        $userGroupIds     = $CryptoUser->getCryptoGroupIds();
        $passwordGroupIds = $this->getAccessGroupsIds();

        $hasGroupAccess = !empty(array_intersect($passwordGroupIds, $userGroupIds));

        if (!$hasGroupAccess) {
            return false;
        }

        $isEligibleForSecurityClass = $this->getSecurityClass()->isUserEligible($CryptoUser);

        if (!$isEligibleForSecurityClass) {
            return false;
        }

        $SecurityClass = $this->getSecurityClass();

        foreach ($passwordGroupIds as $passwordGroupId) {
            $PasswordCryptoGroup = CryptoActors::getCryptoGroup($passwordGroupId);

            if ($PasswordCryptoGroup->hasCryptoUserAccess($SecurityClass, $CryptoUser)) {
                return true;
            }
        }

        return false;
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
        QUI::getDataBase()->delete(
            Tables::usersToPasswords(),
            [
                'userId' => $CryptoUser->getId(),
                'dataId' => $this->id,
            ]
        );

        // only remove meta table entry if the user does not have access to this password via a group
        if (!$this->hasPasswordAccessViaGroup($CryptoUser)) {
            $this->removeMetaTableEntry($CryptoUser);
        }

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
        QUI::getDataBase()->delete(
            Tables::groupsToPasswords(),
            [
                'groupId' => $CryptoGroup->getId(),
                'dataId'  => $this->id,
            ]
        );

        /** @var CryptoUser $CryptoUser */
        foreach ($CryptoGroup->getCryptoUsers() as $CryptoUser) {
            if (!$this->hasPasswordAccess($CryptoUser)) {
                $this->removeMetaTableEntry($CryptoUser);
            }
        }

        return true;
    }

    /**
     * Get IDs of users that can access this password
     *
     * Includes users with direct password access AND access via group
     *
     * @return array
     */
    public function getAccessUserIds()
    {
        $userIds = $this->getDirectAccessUserIds();

        $accessGroupIds = $this->getAccessGroupsIds();
        $Groups         = QUI::getGroups();

        foreach ($accessGroupIds as $groupId) {
            $Group   = $Groups->get($groupId);
            $userIds = array_merge($userIds, $Group->getUserIds());
        }

        return array_unique($userIds);
    }

    /**
     * Get IDs of users that have owner permissions for this password
     *
     * @return array
     */
    public function getOwnerUserIds()
    {
        $currentOwnerId   = $this->getSecretAttribute('ownerId');
        $currentOwnerType = $this->getSecretAttribute('ownerType');

        if ($currentOwnerType === self::OWNER_TYPE_USER) {
            return [$currentOwnerId];
        }

        return CryptoActors::getCryptoGroup($currentOwnerId)->getUserIds();
    }

    /**
     * Get password owner
     *
     * @return CryptoUser|CryptoGroup
     */
    public function getOwner()
    {
        $currentOwnerId   = $this->getSecretAttribute('ownerId');
        $currentOwnerType = $this->getSecretAttribute('ownerType');

        if ($currentOwnerType === self::OWNER_TYPE_USER) {
            return CryptoActors::getCryptoUser($currentOwnerId);
        }

        return CryptoActors::getCryptoGroup($currentOwnerId);
    }

    /**
     * Get password owner type
     *
     * @return int - see Sequry\Core\Password::OWNER_TYPE_*
     */
    public function getOwnerType()
    {
        return $this->getSecretAttribute('ownerType');
    }

    /**
     * Get IDs of users that have (direct!) access to this password
     *
     * @return array
     */
    protected function getDirectAccessUserIds()
    {
        $userIds = [];

        $result = QUI::getDataBase()->fetch([
            'select' => [
                'userId'
            ],
            'from'   => Tables::usersToPasswords(),
            'where'  => [
                'dataId' => $this->id
            ]
        ]);

        foreach ($result as $row) {
            $userIds[] = $row['userId'];
        }

        return array_unique($userIds);
    }

    /**
     * Get IDs of groups that have access to this password
     *
     * @return array
     */
    protected function getAccessGroupsIds()
    {
        $groupIds = [];

        $result = QUI::getDataBase()->fetch([
            'select' => [
                'groupId'
            ],
            'from'   => Tables::groupsToPasswords(),
            'where'  => [
                'dataId' => $this->id
            ]
        ]);

        foreach ($result as $row) {
            $groupIds[] = $row['groupId'];
        }

        return $groupIds;
    }

    /**
     * Create entry in meta data table for this password for a specific user
     *
     * @param CryptoUser $CryptoUser - User the entry is created for
     * @return void
     *
     * @throws QUI\Exception
     */
    public function createMetaTableEntry(CryptoUser $CryptoUser)
    {
        $CryptoUser->createMetaTableEntry($this);
    }

    /**
     * Remove entry in meta data table for this password for a specific user
     *
     * @param CryptoUser $CryptoUser
     * @return void
     *
     * @throws QUI\Exception
     */
    protected function removeMetaTableEntry(CryptoUser $CryptoUser)
    {
//        if ($this->hasPasswordAccess($CryptoUser)) {
//            throw new QUI\Exception(array(
//                'sequry/core',
//                'exception.password.remove.meta.entry.user.has.access',
//                array(
//                    'userId'     => $CryptoUser->getId(),
//                    'userName'   => $CryptoUser->getUsername(),
//                    'passwordId' => $this->id
//                )
//            ));
//        }

        $CryptoUser->removeMetaTableEntry($this);
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

        $this->PasswordKey = $this->getUser()->getPasswordAccessKey($this->id);

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
        $OwnerActor   = $this->getOwner();
        $PasswordUser = $this->getUser();

        switch ($permission) {
            case self::PERMISSION_VIEW:
                return $this->hasPasswordAccess($PasswordUser);
                break;
            case self::PERMISSION_EDIT:
                return $this->isOwner($PasswordUser);
                break;

            case self::PERMISSION_DELETE:
                if ($this->getUser()->isSU()) {
                    return true;
                }

                if ($OwnerActor instanceof CryptoUser) {
                    return $this->isOwner($PasswordUser);
                }

                /** @var CryptoGroup $OwnerActor */
                if (!$PasswordUser->isInGroup($OwnerActor->getId())) {
                    return false;
                }

                if (!Permission::hasPermission(Permissions::PASSWORDS_DELETE_GROUP)
                    && !$OwnerActor->isAdminUser($PasswordUser)) {
                    return false;
                }

                return true;

            case self::PERMISSION_SHARE:
                if (!Permission::hasPermission(Permissions::PASSWORDS_SHARE)) {
                    return false;
                }

                return $this->isOwner($PasswordUser);
                break;

            case self::PERMISSION_SHARE_GROUP:
                if (!Permission::hasPermission(Permissions::PASSWORDS_SHARE_GROUP)
                    && !$OwnerActor->isAdminUser($PasswordUser)) {
                    return false;
                }

                return $this->isOwner($PasswordUser);
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
     * @throws \Sequry\Core\Exception\PermissionDeniedException
     */
    protected function permissionDenied()
    {
        // @todo eigenen 401 fehlercode einfügen
        throw new PermissionDeniedException([
            'sequry/core',
            'exception.password.permission.denied'
        ]);
    }

    /**
     * Sets a secret attribute - secret attributes are attributes that are to be encrypted
     *
     * @param string $k
     * @param mixed $v
     */
    protected function setSecretAttribute($k, $v)
    {
        if (is_string($v) || is_numeric($v)) {
            $v = new HiddenString($v);
        }

        if (is_array($v)) {
            $v = new HiddenString(json_encode($v));
        }

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
        if (!array_key_exists($k, $this->secretAttributes)) {
            return false;
        }

        /** @var HiddenString $v */
        $v = $this->secretAttributes[$k];
        $v = $v->getString();

        if (is_numeric($v)) {
            $v = (int)$v;
        }

        if (Utils::isJson($v)) {
            $v = json_decode($v, true);
        }

        return $v;
    }

    /**
     * Returns all secret attributes - secret attributes are attributes that are to be encrypted
     *
     * @return array
     */
    protected function getSecretAttributes()
    {
        $secretAttributes = [];

        foreach ($this->secretAttributes as $k => $v) {
            $secretAttributes[$k] = $this->getSecretAttribute($k);
        }

        return $secretAttributes;
    }

    /**
     * Decrypt password sensitive data
     *
     * @param Key $Key (optional) - Decrptyion Key; if omitted try to get Key from SessionUser
     * @throws QUI\Exception
     */
    public function decrypt($Key = null)
    {
        if ($this->decrypted) {
            return;
        }

        if (is_null($Key)) {
            $this->SecurityClass->checkAuthentication();
            $PasswordKey = $this->getPasswordKey();
        } else {
            $PasswordKey = $Key;
        }

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
            throw new QUI\Exception([
                'sequry/core',
                'exception.password.acces.data.decryption.fail',
                [
                    'passwordId' => $this->id
                ]
            ]);
        }

        $this->setSecretAttributes([
            'payload'    => $contentDecrypted['payload'],
            'history'    => $contentDecrypted['history'],
            'sharedWith' => $contentDecrypted['sharedWith']
        ]);

        $this->decrypted = true;
    }

    /**
     * Get user that currently handles this password
     *
     * @return CryptoUser
     */
    protected function getUser()
    {
        if (!is_null($this->User)) {
            return $this->User;
        }

        $this->User = CryptoActors::getCryptoUser();

        return $this->User;
    }

    /**
     * Increase view counter by 1
     *
     * @return void
     */
    public function increasePublicViewCount()
    {
        if (!$this->hasPermission(self::PERMISSION_VIEW)) {
            $this->permissionDenied();
        }

        $currentViewCount = $this->getAttribute('viewCount');

        if (empty($currentViewCount)) {
            $currentViewCount = 0;
        }

        QUI::getDataBase()->update(
            Tables::passwords(),
            [
                'viewCount' => ++$currentViewCount
            ],
            [
                'id' => $this->id
            ]
        );

        $this->setAttribute('viewCount', $currentViewCount);
    }

    /**
     * Check if a PasswordLink can be created for this Password
     *
     * @return bool
     */
    public function canBeLinked()
    {
        return $this->isOwner(CryptoActors::getCryptoUser());
    }
}
