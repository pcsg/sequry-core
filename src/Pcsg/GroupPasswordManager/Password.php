<?php

/**
 * This file contains \QUI\Kapitalschutz\Events
 */

namespace Pcsg\GroupPasswordManager;

use ParagonIE\Halite\Asymmetric\Crypto;
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
use Pcsg\GroupPasswordManager\Handler\Categories;

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
    protected $secretAttributes = array();

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

        $passwordData = current($result);

        // check integrity/authenticity of password data
        $passwordDataMAC = $passwordData['MAC'];

        // (re)calculate MAC from actual data
        $macFields = SymmetricCrypto::decrypt(
            $passwordData['MACFields'],
            new Key(Utils::getSystemPasswordAuthKey())
        );

        $macFields = json_decode($macFields, true);
        $macData   = array();

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
            implode('', $macData),
            Utils::getSystemPasswordAuthKey()
        );

        if (!MAC::compare($passwordDataMAC, $passwordDataMACActual)) {
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
        ));

        // set categories
        if (!empty($passwordData['categoryIds'])) {
            $this->setAttribute('categoryIds', explode(',', trim($passwordData['categoryIds'], ',')));
        }

        if (!empty($passwordData['categories'])) {
            $this->setAttribute('categories', explode(',', trim($passwordData['categories'], ',')));
        }

        // ownerId and ownerTye are additionally saved as secret attributes
        // because they may not be altered via public "setAttribute()"-method
        $this->setSecretAttributes(array(
            'ownerId'   => $passwordData['ownerId'],
            'ownerType' => $passwordData['ownerType']
        ));

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
            'securityClassId' => $this->SecurityClass->getId(),
            'categoryIds'     => $this->getAttribute('categoryIds'),
            'createUserId'    => $this->getAttribute('createUserId'),
            'createDate'      => $this->getAttribute('createDate'),
            'editUserId'      => $this->getAttribute('editUserId'),
            'editDate'        => $this->getAttribute('editDate')
        );

        // private category ids
        $metaData                       = $this->User->getPasswordMetaData($this->id);
        $viewData['categoryIdsPrivate'] = explode(',', trim($metaData['categoryIds'], ','));
        $viewData['favorite']           = $metaData['favorite'];

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
            'securityClassId' => $this->SecurityClass->getId(),
            'categoryIds'     => $this->getAttribute('categoryIds')
        );

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

        if (isset($passwordData['securityClassId'])
            && !empty($passwordData['securityClassId'])
        ) {
            $SecurityClass       = Authentication::getSecurityClass((int)$passwordData['securityClassId']);
            $this->SecurityClass = $SecurityClass;
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

                case 'categoryIds':
                    if (empty($v)) {
                        $this->setAttribute('categories', null);
                        $this->setAttribute('categoryIds', null);

                        break;
                    }

                    if (!is_array($v)) {
                        break;
                    }

                    $family = array();

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
     * Get info about password access
     *
     * - Owner
     * - How is the password accessed (which groups)
     *
     * @return array
     */
    public function getAccessInfo()
    {
        if (!$this->hasPermission(self::PERMISSION_VIEW)) {
            $this->permissionDenied();
        }

        $accessGroups = array();

        foreach ($this->getAccessGroupsIds() as $groupId) {
            $accessGroups[] = array(
                'id'   => $groupId,
                'name' => QUI::getGroups()->get($groupId)->getName()
            );
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

        return array(
            'owner'       => array(
                'id'   => $ownerId,
                'name' => $name,
                'type' => $ownerType
            ),
            'access'      => array(
                'user'   => in_array($this->getUser()->getId(), $this->getDirectAccessUserIds()),
                'groups' => $accessGroups
            ),
            'userIsOwner' => $ownerId == $this->getUser()->getId()
        );
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
            json_encode($this->getSecretAttributes()),
            $this->getPasswordKey()
        );

        $passwordData = array(
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
        );

        // encrypt fields used for MAC creation (MACFields)
        $macFields = SymmetricCrypto::encrypt(
            json_encode(array_keys($passwordData)),
            new Key(Utils::getSystemPasswordAuthKey())
        );

        // calculate new MAC
        $newMAC = MAC::create(
            implode('', $passwordData),
            Utils::getSystemPasswordAuthKey()
        );

        $passwordData['MAC']       = $newMAC;
        $passwordData['MACFields'] = $macFields;

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

            // delete meta data entries
            $DB->delete(
                QUI::getDBTableName(Tables::USER_TO_PASSWORDS_META),
                array(
                    'dataId' => $this->id
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
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.change.owner.no.permission'
            ));
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
                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.password.change.owner.group.to.user'
                    ));
                }

                $CryptoUser = CryptoActors::getCryptoUser($id);

                if (!$this->SecurityClass->isUserEligible($CryptoUser)) {
                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.password.create.access.user.not.eligible',
                        array(
                            'userId'             => $CryptoUser->getId(),
                            'userName'           => $CryptoUser->getName(),
                            'securityClassId'    => $this->SecurityClass->getId(),
                            'securityClassTitle' => $this->SecurityClass->getAttribute('title')
                        )
                    ));
                }

                if ($currentOwnerId === $id) {
                    return true;
                }

                if ($checkGroupSharePermission
                    && !$this->hasPermission(self::PERMISSION_SHARE_GROUP)
                ) {
                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.password.change.owner.no.group.share.permission'
                    ));
                }
                break;

            case self::OWNER_TYPE_GROUP:
            case 'group':
                $CryptoGroup = CryptoActors::getCryptoGroup($id);

                if (!$this->SecurityClass->isGroupEligible($CryptoGroup)) {
                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.password.create.access.group.not.eligible',
                        array(
                            'groupId'            => $CryptoGroup->getId(),
                            'groupName'          => $CryptoGroup->getAttribute('name'),
                            'securityClassId'    => $this->SecurityClass->getId(),
                            'securityClassTitle' => $this->SecurityClass->getAttribute('title')
                        )
                    ));
                }

                if ($currentOwnerId === $id
                    && $currentOwnerType === self::OWNER_TYPE_GROUP
                ) {
                    return true;
                }

                if ($checkGroupSharePermission
                    && !$this->hasPermission(self::PERMISSION_SHARE_GROUP)
                ) {
                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.password.change.owner.no.group.share.permission'
                    ));
                }
                break;

            default:
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.password.change.owner.wrong.type'
                ));
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
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.password.change.owner.wrong.type'
                ));
        }

        // set new owner
        $this->setSecretAttributes(array(
            'newOwnerId'   => $newOwnerId,
            'newOwnerType' => $newOwnerType
        ));

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
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.no.share.permission'
            ));
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
                    'userName'           => $User->getName(),
                    'securityClassId'    => $this->SecurityClass->getId(),
                    'securityClassTitle' => $this->SecurityClass->getAttribute('title')
                )
            ));
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
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.no.share.permission'
            ));
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

        if ($this->SecurityClass->getId() == $SecurityClass->getId()) {
            return;
        }

        $ownerId = $this->getSecretAttribute('ownerId');

        switch ($this->getSecretAttribute('ownerType')) {
            case self::OWNER_TYPE_USER:
                $CryptoUser = CryptoActors::getCryptoUser($ownerId);

                if (!$SecurityClass->isUserEligible($CryptoUser)) {
                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.password.setsecurityclass.owner.user.not.eligible',
                        array(
                            'securityClassId'    => $SecurityClass->getId(),
                            'securityClassTitle' => $SecurityClass->getAttribute('title')
                        )
                    ));
                }
                break;

            case self::OWNER_TYPE_GROUP:
                $CryptoGroup = CryptoActors::getCryptoGroup($ownerId);

                if (!$SecurityClass->isGroupEligible($CryptoGroup)) {
                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.password.setsecurityclass.owner.group.not.eligible',
                        array(
                            'securityClassId'    => $SecurityClass->getId(),
                            'securityClassTitle' => $SecurityClass->getAttribute('title')
                        )
                    ));
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

            $userGroupIds     = $CryptoActor->getCryptoGroupIds();
            $passwordGroupIds = $this->getAccessGroupsIds();

            return !empty(array_intersect($passwordGroupIds, $userGroupIds));
        }

        if ($CryptoActor instanceof CryptoGroup) {
            return in_array($CryptoActor->getId(), $this->getAccessGroupsIds());
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
            Tables::USER_TO_PASSWORDS,
            array(
                'userId' => $CryptoUser->getId(),
                'dataId' => $this->id,
            )
        );

        $this->removeMetaTableEntry($CryptoUser);

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
            Tables::GROUP_TO_PASSWORDS,
            array(
                'groupId' => $CryptoGroup->getId(),
                'dataId'  => $this->id,
            )
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
        $currentOwnerId   = (int)$this->getSecretAttribute('ownerId');
        $currentOwnerType = (int)$this->getSecretAttribute('ownerType');

        if ($currentOwnerType === self::OWNER_TYPE_USER) {
            return array($currentOwnerId);
        }

        return CryptoActors::getCryptoGroup($currentOwnerId)->getUserIds();
    }

    /**
     * Get IDs of users that have (direct!) access to this password
     *
     * @return array
     */
    protected function getDirectAccessUserIds()
    {
        $userIds = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'userId'
            ),
            'from'   => Tables::USER_TO_PASSWORDS,
            'where'  => array(
                'dataId' => $this->id
            )
        ));

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
     * Create entry in meta data table for this password for a specific user
     *
     * @param CryptoUser $CryptoUser - User the entry is created for
     * @return void
     *
     * @throws QUI\Exception
     */
    public function createMetaTableEntry(CryptoUser $CryptoUser)
    {
        $metaData = $CryptoUser->getPasswordMetaData($this->id);

        if (!empty($metaData)) {
            return;
        }

        QUI::getDataBase()->insert(
            QUI::getDBTableName(Tables::USER_TO_PASSWORDS_META),
            array(
                'userId'     => $CryptoUser->getId(),
                'dataId'     => $this->id,
                'accessDate' => time()
            )
        );
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
//                'pcsg/grouppasswordmanager',
//                'exception.password.remove.meta.entry.user.has.access',
//                array(
//                    'userId'     => $CryptoUser->getId(),
//                    'userName'   => $CryptoUser->getUsername(),
//                    'passwordId' => $this->id
//                )
//            ));
//        }

        QUI::getDataBase()->delete(
            QUI::getDBTableName(Tables::USER_TO_PASSWORDS_META),
            array(
                'userId' => $CryptoUser->getId(),
                'dataId' => $this->id
            )
        );
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
        $ownerType = (int)$this->getAttribute('ownerType');

        switch ($permission) {
            case self::PERMISSION_VIEW:
                return $this->hasPasswordAccess($this->getUser());
                break;
            case self::PERMISSION_EDIT:
                return $this->isOwner($this->getUser());
                break;

            case self::PERMISSION_DELETE:
                if ($this->getUser()->isSU()) {
                    return true;
                }

                if ($ownerType === self::OWNER_TYPE_USER) {
                    return $this->isOwner($this->getUser());
                }

                if (!Permission::hasPermission(Permissions::PASSWORDS_DELETE_GROUP)) {
                    return false;
                }

                return $this->isOwner($this->getUser());

            case self::PERMISSION_SHARE:
                if (!Permission::hasPermission(Permissions::PASSWORDS_SHARE)) {
                    return false;
                }

                return $this->isOwner($this->getUser());
                break;

            case self::PERMISSION_SHARE_GROUP:
                if (!Permission::hasPermission(Permissions::PASSWORDS_SHARE_GROUP)) {
                    return false;
                }

                return $this->isOwner($this->getUser());
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
        if ($this->decrypted) {
            return;
        }

        if (!$this->SecurityClass->isAuthenticated()) {
            // @todo eigenen 401 error code einfügen
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.user.not.authenticated',
                array(
                    'id'     => $this->id,
                    'userId' => $this->getUser()->getId()
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
            QUI::getDBTableName(Tables::PASSWORDS),
            array(
                'viewCount' => ++$currentViewCount
            ),
            array(
                'id' => $this->id
            )
        );

        $this->setAttribute('viewCount', $currentViewCount);
    }
}
