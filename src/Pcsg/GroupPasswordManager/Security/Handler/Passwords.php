<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Security\Handler\Passwords
 */

namespace Pcsg\GroupPasswordManager\Security\Handler;

use Pcsg\GroupPasswordManager\Actors\CryptoGroup;
use Pcsg\GroupPasswordManager\Constants\Permissions;
use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Actors\CryptoUser;
use Pcsg\GroupPasswordManager\Password;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Authentication\SecurityClass;
use Pcsg\GroupPasswordManager\Security\HiddenString;
use Pcsg\GroupPasswordManager\Security\Keys\AuthKeyPair;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\SecretSharing;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use Pcsg\GroupPasswordManager\Handler\Categories;
use QUI;
use QUI\Permissions\Permission;
use QUI\Cache\Manager as CacheManager;

/**
 * Class for for managing passwords
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Passwords
{
    /**
     * Password objects
     *
     * @var array
     */
    protected static $passwords = array();

    /**
     * Create a new password
     *
     * @param array $passwordData - password data
     * @return null
     * @throws QUI\Exception
     */
    public static function createPassword($passwordData)
    {
        if (!Permission::hasPermission(Permissions::PASSWORDS_CREATE)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwords.create.no.permission'
            ));
        }

        // check if necessary data is given
        if (empty($passwordData)
            || !is_array($passwordData)
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwords.create.missing.data'
            ));
        }

        // security class check
        if (!isset($passwordData['securityClassId'])
            || empty($passwordData['securityClassId'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwords.create.missing.securityclass'
            ));
        }

        $SecurityClass = Authentication::getSecurityClass(
            $passwordData['securityClassId']
        );

        // owner check
        if (!isset($passwordData['owner'])
            || empty($passwordData['owner'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwords.create.missing.owner'
            ));
        }

        $owner = $passwordData['owner'];

        if (!isset($owner['id'])
            || empty($owner['id'])
            || !isset($owner['type'])
            || empty($owner['type'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwords.create.owner.incorrect.data'
            ));
        }

        $ownerId   = (int)$owner['id'];
        $ownerType = $owner['type'];

        if (!Permission::hasPermission(Permissions::PASSWORDS_SHARE)
            && ($ownerId !== (int)QUI::getUserBySession()->getId()
                || $ownerType == 'group')
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwords.create.no.share.rights'
            ));
        }

        // title check
        if (!isset($passwordData['title'])
            || empty($passwordData['title'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwords.create.missing.title'
            ));
        }

        // type check
        if (!isset($passwordData['dataType'])
            || empty($passwordData['dataType'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwords.create.missing.type'
            ));
        }

        // payload check
        if (!isset($passwordData['payload'])
            || empty($passwordData['payload'])
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.passwords.create.missing.payload'
            ));
        }

        switch ($owner['type']) {
            case 'user':
                try {
                    $OwnerActor = CryptoActors::getCryptoUser($ownerId);

                    if (!$SecurityClass->isUserEligible($OwnerActor)) {
                        throw new QUI\Exception();
                    }

                    $ownerType = Password::OWNER_TYPE_USER;
                } catch (QUI\Exception $Exception) {
                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.passwords.create.owner.user.incorrect.data',
                        array(
//                            'userId'             => $OwnerActor->getId(),
//                            'userName'           => $OwnerActor->getUsername(),
                            'securityClassId'    => $SecurityClass->getId(),
                            'securityClassTitle' => $SecurityClass->getAttribute('title')
                        )
                    ));
                }
                break;

            case 'group':
                $OwnerActor = CryptoActors::getCryptoGroup($ownerId);

                if (!$SecurityClass->isGroupEligible($OwnerActor)) {
                    throw new QUI\Exception();
                }

                $ownerType = Password::OWNER_TYPE_GROUP;
                break;

            default:
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.passwords.create.owner.group.incorrect.data'
                ));
        }

        // set initial content for password
        $passwordContent = array(
            'ownerId'    => $ownerId,
            'ownerType'  => $ownerType,
            'payload'    => $passwordData['payload'],
            'sharedWith' => array(
                'users'  => array(),
                'groups' => array()
            ),
            'history'    => array()
        );

        // generate password key
        $PasswordKey = SymmetricCrypto::generateKey();

        // encrypt password data and calculate MAC
        $passwordContentEncrypted = SymmetricCrypto::encrypt(
            new HiddenString(json_encode($passwordContent)),
            $PasswordKey
        );

        $passwordEntry = array(
            'ownerId'         => $ownerId,
            'ownerType'       => $ownerType,
            'securityClassId' => $SecurityClass->getId(),
            'title'           => $passwordData['title'],
            'description'     => $passwordData['description'],
            'dataType'        => $passwordData['dataType'],
            'cryptoData'      => $passwordContentEncrypted,
            'categories'      => null,
            'categoryIds'     => null,
            'createUserId'    => QUI::getUserBySession()->getId(),
            'createDate'      => time(),
            'editUserId'      => QUI::getUserBySession()->getId(),
            'editDate'        => time()
        );

        if (isset($passwordData['categoryIds'])
            && !empty($passwordData['categoryIds']
                      && is_array($passwordData['categoryIds']))
        ) {
            $family = array();

            foreach ($passwordData['categoryIds'] as $catId) {
                $family = array_merge(
                    $family,
                    Categories::getPublicCategoryFamilyList($catId)
                );
            }

            if (!empty($family)) {
                $passwordEntry['categories'] = ',' . implode(',', array_unique($family)) . ',';
            }

            $passwordEntry['categoryIds'] = ',' . implode(',', $passwordData['categoryIds']) . ',';
        }

        // save fields that have been used for MAC creation
        $macFields = SymmetricCrypto::encrypt(
            new HiddenString(json_encode(array_keys($passwordEntry))),
            Utils::getSystemPasswordAuthKey()
        );

        // calculate MAC
        $mac = MAC::create(
            new HiddenString(implode('', $passwordEntry)),
            Utils::getSystemPasswordAuthKey()
        );

        $passwordEntry['MAC']       = $mac;
        $passwordEntry['MACFields'] = $macFields;

        // write to database
        $DB = QUI::getDataBase();

        try {
            $DB->insert(
                Tables::passwords(),
                $passwordEntry
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Error writing password to database: ' . $Exception->getMessage()
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.create.general.error'
            ));
        }

        $passwordId = QUI::getPDO()->lastInsertId();

        /*** Create Password Access ***/
        // write access data to database
        switch ($ownerType) {
            case Password::OWNER_TYPE_USER:
                // split key
                $passwordKeyParts = SecretSharing::splitSecret(
                    $PasswordKey->getValue(),
                    $SecurityClass->getAuthPluginCount(),
                    $SecurityClass->getRequiredFactors()
                );

                /** @var CryptoUser $OwnerActor */
                $authKeyPairs = $OwnerActor->getAuthKeyPairsBySecurityClass($SecurityClass);
                $i            = 0;

                /** @var AuthKeyPair $UserAuthKeyPair */
                foreach ($authKeyPairs as $UserAuthKeyPair) {
                    try {
                        $passwordKeyPart = $passwordKeyParts[$i++];

                        $encryptedPasswordKeyPart = AsymmetricCrypto::encrypt(
                            new HiddenString($passwordKeyPart),
                            $UserAuthKeyPair
                        );

                        $dataAccessEntry = array(
                            'userId'    => $OwnerActor->getId(),
                            'dataId'    => $passwordId,
                            'dataKey'   => $encryptedPasswordKeyPart,
                            'keyPairId' => $UserAuthKeyPair->getId()
                        );

                        $dataAccessEntry['MAC'] = MAC::create(
                            new HiddenString(implode('', $dataAccessEntry)),
                            Utils::getSystemKeyPairAuthKey()
                        );

                        $DB->insert(
                            Tables::usersToPasswords(),
                            $dataAccessEntry
                        );
                    } catch (\Exception $Exception) {
                        // on error delete password entry
                        $DB->delete(
                            Tables::passwords(),
                            array(
                                'id' => $passwordId
                            )
                        );

                        QUI\System\Log::addError(
                            'Error writing password key parts to database: ' . $Exception->getMessage()
                        );

                        throw new QUI\Exception(array(
                            'pcsg/grouppasswordmanager',
                            'exception.password.create.general.error'
                        ));
                    }
                }
                break;

            case Password::OWNER_TYPE_GROUP:
                try {
                    /** @var CryptoGroup $OwnerActor */
                    $GroupKeyPair = $OwnerActor->getKeyPair($SecurityClass);

                    // encrypt password payload key with group public key
                    $passwordKeyEncrypted = AsymmetricCrypto::encrypt(
                        $PasswordKey->getValue(),
                        $GroupKeyPair
                    );

                    $dataAccessEntry = array(
                        'groupId' => $OwnerActor->getId(),
                        'dataId'  => $passwordId,
                        'dataKey' => $passwordKeyEncrypted
                    );

                    $dataAccessEntry['MAC'] = MAC::create(
                        new HiddenString(implode('', $dataAccessEntry)),
                        Utils::getSystemKeyPairAuthKey()
                    );

                    $DB->insert(
                        Tables::groupsToPasswords(),
                        $dataAccessEntry
                    );
                } catch (\Exception $Exception) {
                    // on error delete password entry
                    $DB->delete(
                        Tables::passwords(),
                        array(
                            'id' => $passwordId
                        )
                    );

                    QUI\System\Log::addError(
                        'Error writing password key parts to database: ' . $Exception->getMessage()
                    );

                    throw new QUI\Exception(array(
                        'pcsg/grouppasswordmanager',
                        'exception.password.create.general.error'
                    ));
                }
                break;
        }

        // set meta entries to all access users
        $Password = self::get($passwordId);

        foreach ($Password->getAccessUserIds() as $userId) {
            $CryptoUser = CryptoActors::getCryptoUser($userId);
            $Password->createMetaTableEntry($CryptoUser);
        }

        // if the creating user put the password in private categories -> add it to these categories
        if (isset($passwordData['categoryIdsPrivate'])
            && !empty($passwordData['categoryIdsPrivate'])
        ) {
            Categories::addPasswordToPrivateCategories($Password, $passwordData['categoryIdsPrivate']);
        }

        return $passwordId;
    }

    /**
     * @todo ggf. auch für nicht Session-User
     *
     * Get password object
     *
     * @param integer $id - password id
     * @param CryptoUser $CryptoUser (optional) - CryptoUser that interacts with the password;
     * this user has to have access rights for this password; if omitted, use session user
     * @return Password
     */
    public static function get($id, $CryptoUser = null)
    {
        if (isset(self::$passwords[$id])) {
            return self::$passwords[$id];
        }

        self::$passwords[$id] = new Password($id);

        return self::$passwords[$id];
    }

    /**
     * Checks if a user has access to a password
     *
     * @param QUI\Users\User $User
     * @param integer $passwordId - password ID
     *
     * @return bool - true if user has access; false if user does not have access
     */
    public static function hasPasswordAccess($User, $passwordId)
    {
        $result = QUI::getDataBase()->fetch(array(
            'count' => 1,
            'from'  => Tables::usersToPasswords(),
            'where' => array(
                'dataId' => (int)$passwordId,
                'userId' => $User->getId()
            )
        ));

        $count = current(current($result));

        return $count > 0;
    }

    /**
     * Get security class of password object
     *
     * @param integer $passwordId - Password ID
     * @return SecurityClass
     * @throws QUI\Exception
     */
    public static function getSecurityClass($passwordId)
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'securityClassId'
            ),
            'from'   => Tables::passwords(),
            'where'  => array(
                'id' => $passwordId
            )
        ));

        if (empty($result)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.not.found',
                array(
                    'passwordId' => $passwordId
                )
            ), 404);
        }

        $data = current($result);

        return Authentication::getSecurityClass($data['securityClassId']);
    }

    /**
     * Get IDs of all SecurityClasses of multiple passwords
     *
     * @param array $passwordIds
     * @return array - SecurityClass IDs
     */
    public static function getSecurityClassIds($passwordIds)
    {
        if (empty($passwordIds)) {
            return array();
        }

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'securityClassId'
            ),
            'from'   => Tables::passwords(),
            'where'  => array(
                'id' => array(
                    'type'  => 'IN',
                    'value' => $passwordIds
                )
            )
        ));

        if (empty($result)) {
            return array();
        }

        $ids = array();

        foreach ($result as $row) {
            $ids[] = $row['securityClassId'];
        }

        return array_unique($ids);
    }

    /**
     * Checks if the system is set up to use all Sequry features
     *
     * @return bool
     */
    public static function isSetupComplete()
    {
        $cacheName = 'pcsg/grouppasswordmanager/setupComplete';

        try {
            return CacheManager::get($cacheName);
        } catch (\Exception $Exception) {
            // execute completion check
        }

        $setupComplete = true;

        // check if a SecurityClass exists
        $securityClasses = Authentication::getSecurityClassesList();

        if (!count($securityClasses)) {
            $setupComplete = false;
        }

        // save in cache once setup is complete
        if ($setupComplete) {
            CacheManager::set($cacheName, true);
        }

        return $setupComplete;
    }
}
