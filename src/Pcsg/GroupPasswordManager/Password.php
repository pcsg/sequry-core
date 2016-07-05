<?php

/**
 * This file contains \QUI\Kapitalschutz\Events
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Authentication\Plugin;
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
     * @var null
     */
    protected $User = null;

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

        $this->ownerId    = $contentDecrypted['ownerId'];
        $this->ownerType  = $contentDecrypted['ownerType'];
        $this->payload    = $contentDecrypted['payload'];
        $this->history    = $contentDecrypted['history'];
        $this->sharedWith = $contentDecrypted['sharedWith'];
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
            'payload'     => $this->payload
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
            'payload'     => $this->payload,
            'ownerId'     => $this->ownerId,
            'ownerType'   => $this->ownerType
        );

        return $data;
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

        return $this->sharedWith;
    }

    /**
     * Set share users and groups
     *
     * @param array $shareData
     */
    public function setShareData($shareData)
    {
        $validActors = array(
            'users'  => array(),
            'groups' => array()
        );

        foreach ($shareData as $shareActor) {
            if (!isset($shareActor['type'])
                || empty($shareActor['type'])
                || !isset($shareActor['id'])
                || empty($shareActor['id'])
            ) {
                continue;
            }

            switch ($shareActor['type']) {
                case self::OWNER_TYPE_USER:
                    try {
                        $User = QUI::getUsers()->get($shareActor['id']);

                        if ($this->SecurityClass->isUserEligible($User)) {
                            $validActors['users'][] = $User->getId();
                        }
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
                        $Group = QUI::getGroups()->get($shareActor['id']);

                        if ($this->SecurityClass->isGroupEligible($Group)) {
                            $validActors['groups'][] = $Group->getId();
                        }
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

        $data = array(
            'title'           => $this->title,
            'description'     => $this->description,
            'ownerId'         => $this->ownerId,
            'ownerType'       => $this->ownerType,
            'payload'         => $this->payload,
            'sharedWith'      => $validActors,
            'history'         => $this->history,
            'securityClassId' => $this->SecurityClass->getId()
        );

        $this->save($data);
    }

    /**
     * Edit password data
     *
     * @param $passwordData
     */
    public function setData($passwordData)
    {
        $sanitizedData = array();

        foreach ($passwordData as $k => $v) {
            switch ($k) {
                // security class
                case 'securityClassId':
                    // @todo re-encrypt for every owner and access user with new security class
                    $sanitizedData['securityClassId'] = $this->SecurityClass->getId();
                    break;

                case 'title':
                    $sanitizedData['title'] = $this->title;

                    if (is_string($v)
                        && !empty($v)
                    ) {
                        $sanitizedData['title'] = $v;
                    }
                    break;

                case 'description':
                    $sanitizedData['description'] = $this->description;

                    if (is_string($v)) {
                        $sanitizedData['description'] = $v;
                    }
                    break;

                case 'payload':
                    $sanitizedData['payload'] = $this->payload;
                    $sanitizedData['history'] = $this->history;

                    if (is_string($v)
                        && !empty($v)
                    ) {
                        if ($this->payload !== $v) {
                            $sanitizedData['payload'] = $v;

                            // write history entry if payload changes
                            $sanitizedData['history']   = $this->history;
                            $sanitizedData['history'][] = array(
                                'timestamp' => time(),
                                'value'     => $this->payload
                            );
                        }
                    }
                    break;

                case 'owner':
                    $sanitizedData['ownerId']   = $this->ownerId;
                    $sanitizedData['ownerType'] = $this->ownerType;

                    if (is_array($v)
                        && isset($v['id'])
                        && !empty($v['id'])
                        && is_numeric($v['id'])
                        && isset($v['type'])
                        && !empty($v['type'])
                    ) {
                        switch ($v['type']) {
                            case 'user':
                                if ($this->ownerType === self::OWNER_TYPE_GROUP) {
                                    QUI::getMessagesHandler()->addAttention(
                                        QUI::getLocale()->get(
                                            'pcsg/grouppasswordmanager',
                                            'attention.password.edit.owner.group.to.user'
                                        )
                                    );

                                    continue;
                                }

                                try {
                                    $OwnerUser = QUI::getUsers()->get($v['id']);
                                } catch (\Exception $Exception) {
                                    continue;
                                }

                                $sanitizedData['ownerId']   = $OwnerUser->getId();
                                $sanitizedData['ownerType'] = self::OWNER_TYPE_USER;
                                break;

                            case 'group':
                                try {
                                    $OwnerGroup = QUI::getGroups()->get($v['id']);
                                } catch (\Exception $Exception) {
                                    continue;
                                }

                                $sanitizedData['ownerId']   = $OwnerGroup->getId();
                                $sanitizedData['ownerType'] = self::OWNER_TYPE_GROUP;
                                break;
                        }
                    }
                    break;

                default:
            }
        }

        $sanitizedData['sharedWith'] = $this->sharedWith;

        $this->save($sanitizedData);
    }

    /**
     * Save data to password object
     *
     * @param $data
     */
    protected function save($data)
    {
        $cryptoData = array(
            'ownerId'    => $data['ownerId'],
            'ownerType'  => $data['ownerType'],
            'payload'    => $data['payload'],
            'sharedWith' => $data['sharedWith'],
            'history'    => $data['history']
        );

        $PasswordKey = $this->getPasswordKey();

        $cryptoDataEncrypted = SymmetricCrypto::encrypt(
            json_encode($cryptoData),
            $PasswordKey
        );

        $passwordData = array(
            'securityClassId' => $data['securityClassId'],
            'title'           => $data['title'],
            'description'     => $data['description'],
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

        // change ownership
        if ((int)$data['ownerId'] !== (int)$this->ownerId) {
            // delete access data for old owner(s)
            switch ($this->ownerType) {
                case self::OWNER_TYPE_USER:
                    $User = CryptoActors::getCryptoUser($this->ownerId);

                    try {
                        $DB->delete(
                            Tables::USER_TO_PASSWORDS,
                            array(
                                'userId' => $User->getId(),
                                'dataId' => $this->id
                            )
                        );
                    } catch (\Exception $Exception) {
                        QUI\System\Log::addError(
                            'Could not delete access data for user #' . $User->getId() . ': '
                            . $Exception->getMessage()
                        );

                        // @todo abbrechen
                    }
                    break;

                case self::OWNER_TYPE_GROUP:
                    $Group = QUI::getGroups()->get($this->ownerId);
                    $users = $Group->getUsers();

                    foreach ($users as $row) {
                        $User = CryptoActors::getCryptoUser($row['id']);

                        try {
                            $DB->delete(
                                Tables::USER_TO_PASSWORDS,
                                array(
                                    'userId' => $User->getId(),
                                    'dataId' => $this->id
                                )
                            );
                        } catch (\Exception $Exception) {
                            QUI\System\Log::addError(
                                'Could not delete access data for user #' . $User->getId() . ': '
                                . $Exception->getMessage()
                            );

                            // @todo abbrechen
                        }
                    }
            }

            // set access data for new owner(s)
            switch ($data['ownerType']) {
                case self::OWNER_TYPE_USER:
                    $User = CryptoActors::getCryptoUser($data['ownerId']);

                    try {
                        $this->createPasswordAccess($User);
                    } catch (\Exception $Exception) {
                        QUI\System\Log::addError(
                            'Could not create access data for user #' . $User->getId() . ': '
                            . $Exception->getMessage()
                        );

                        // @todo abbrechen
                    }
                    break;

                case self::OWNER_TYPE_GROUP:
                    $Group = QUI::getGroups()->get($data['ownerId']);
                    $users = $Group->getUsers();

                    foreach ($users as $row) {
                        $User = CryptoActors::getCryptoUser($row['id']);

                        try {
                            $this->createPasswordAccess($User, $Group->getId());
                        } catch (\Exception $Exception) {
                            QUI\System\Log::addError(
                                'Could not create access data for user #' . $User->getId() . ': '
                                . $Exception->getMessage()
                            );

                            // @todo abbrechen
                        }
                    }
                    break;
            }
        }

        // process sharing
        $currentShareUserIds = $this->sharedWith['users'];
        $newShareUserIds     = array();

        $currentShareGroupIds = $this->sharedWith['groups'];
        $newShareGroupIds     = array();

        foreach ($data['sharedWith'] as $type => $ids) {
            switch ($type) {
                case 'users':
                    foreach ($ids as $id) {
                        if ((int)$id === (int)$this->ownerId) {
                            continue;
                        }

//                        if (in_array($id, $currentShareUserIds)) {
//                            $newShareUserIds[] = $id;
//                            continue;
//                        }

                        try {
                            $CryptoUser = CryptoActors::getCryptoUser($id);
                            $this->createPasswordAccess($CryptoUser);
                            $newShareUserIds[] = $CryptoUser->getId();
                        } catch (\Exception $Exception) {
                            // @todo error log und meldung an user
                        }
                    }
                    break;

                case 'groups':
                    foreach ($ids as $id) {
                        if ((int)$id === (int)$this->ownerId) {
                            continue;
                        }

                        if (in_array($id, $currentShareGroupIds)) {
                            $newShareGroupIds[] = $id;
                            continue;
                        }

                        $Group = QUI::getGroups()->get($id);
                        $users = $Group->getUsers();

                        foreach ($users as $row) {
                            try {
                                $CryptoUser = CryptoActors::getCryptoUser($row['id']);
                                $this->createPasswordAccess($CryptoUser, $Group->getId());
                                $newShareGroupIds[] = $CryptoUser->getId();
                            } catch (\Exception $Exception) {
                                // @todo error log und meldung an user
                            }
                        }
                    }
                    break;
            }
        }

        // delete access from old share users and groups
        $deleteShareUserIds = array_diff($currentShareUserIds, $newShareUserIds);

        foreach ($deleteShareUserIds as $id) {
            // owner access can never be deleted!
            if ((int)$id === (int)$this->ownerId) {
                continue;
            }

            try {
                $DB->delete(
                    Tables::USER_TO_PASSWORDS,
                    array(
                        'userId' => (int)$id,
                        'dataId' => $this->id
                    )
                );
            } catch (\Exception $Exception) {
                // @todo error log und meldung an user
            }
        }

        $deleteShareGroupIds = array_diff($currentShareGroupIds, $newShareGroupIds);

        foreach ($deleteShareGroupIds as $id) {
            try {
                $DB->delete(
                    Tables::USER_TO_PASSWORDS,
                    array(
                        'groupId' => (int)$id,
                        'dataId'  => $this->id
                    )
                );
            } catch (\Exception $Exception) {
                // @todo error log und meldung an user
            }
        }

        $this->title       = $data['title'];
        $this->description = $data['description'];
        $this->payload     = $data['payload'];
        $this->ownerId     = $data['ownerId'];
        $this->ownerType   = $data['ownerType'];
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
     * Creates and inserts password access data database entry for a user
     *
     * @param CryptoUser $User
     * @param integer $groupId (optional) - is user has access via a group
     * @throws QUI\Exception
     */
    protected function createPasswordAccess($User, $groupId = null)
    {

        \QUI\System\Log::writeRecursive("Create access for user " . $User->getId());

        // split key
        $authPlugins = $this->SecurityClass->getAuthPlugins();
        $PasswordKey = $this->getPasswordKey();

        $payloadKeyParts = SecretSharing::splitSecret(
            $PasswordKey->getValue(),
            count($authPlugins)
        );

        // encrypt key parts with user public keys
        $i  = 0;
        $DB = QUI::getDataBase();

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
                    'groupId'   => $groupId,
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
                    'exception.password.get.access.data.general.error'
                ));
            }
        }
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

        // get password access data
        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::USER_TO_PASSWORDS,
            'where' => array(
                'userId' => $this->User->getId(),
                'dataId' => $this->id
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
                        $row['keyPairId'],
                        $row['groupId']
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

            $AuthKeyPair        = new AuthKeyPair($row['keyPairId']);
            $passwordKeyParts[] = AsymmetricCrypto::decrypt(
                $row['dataKey'],
                $AuthKeyPair
            );
        }

        $this->PasswordKey = new Key(
            SecretSharing::recoverSecret($passwordKeyParts)
        );

        // build password key from its parts
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
        switch ($permission) {
            case self::PERMISSION_VIEW:
                if ($this->isOwner()) {
                    return true;
                }

                $uId = $this->User->getId();

                if (in_array($uId, $this->sharedWith['users'])) {
                    return true;
                }

                $groupIds = $this->sharedWith['groups'];

                foreach ($groupIds as $groupId) {
                    if ($this->User->isInGroup($groupId)) {
                        return true;
                    }
                }

                return false;
                break;
            case self::PERMISSION_EDIT:
                return $this->isOwner();
                break;

            case self::PERMISSION_DELETE:
                if ($this->ownerType === self::OWNER_TYPE_USER) {
                    return $this->isOwner();
                }

                try {
                    QUI\Permissions\Permission::hasPermission(
                        'pcsg.gpm.cryptodata.delete'
                    );
                } catch (QUI\Exception $Exception) {
                    return false;
                }

                return true;

            case self::PERMISSION_SHARE:
                if ($this->ownerType === self::OWNER_TYPE_USER) {
                    return $this->isOwner();
                }

                try {
                    QUI\Permissions\Permission::hasPermission(
                        'pcsg.gpm.cryptodata.share'
                    );
                } catch (QUI\Exception $Exception) {
                    return false;
                }

                return true;
                break;

            default:
                return false;
        }
    }

    /**
     * Checks if current password user is password owner
     *
     * @return bool
     */
    protected function isOwner()
    {
        switch ($this->ownerType) {
            case self::OWNER_TYPE_USER:
                return (int)$this->ownerId === (int)$this->User->getId();
                break;

            case self::OWNER_TYPE_GROUP:
                return $this->User->isInGroup((int)$this->ownerId);
                break;

            default:
                return false;
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
}