<?php

/**
 * This file contains \QUI\Kapitalschutz\Events
 */

namespace Pcsg\GroupPasswordManager;

use Monolog\Handler\Curl\Util;
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

        if (!Utils::compareStrings($passwordDataMAC, $passwordDataMACCheck)) {
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

        // get password access data
        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::USER_TO_PASSWORDS,
            'where' => array(
                'userId' => $CryptoUser->getId(),
                'dataId' => $id
            )
        ));

        if (empty($result)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.password.access.data.not.found',
                array(
                    'id'     => $id,
                    'userId' => $CryptoUser->getId()
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

            if (!Utils::compareStrings($accessDataMAC, $accessDataMACCheck)) {
                QUI\System\Log::addCritical(
                    'Password access data (uid #' . $row['userId'] . ', dataId #' . $row['dataId']
                    . ', keyPairId #' . $row['keyPairId'] . ' is possibly altered! MAC mismatch!'
                );

                // @todo eigenen 401 error code
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.password.acces.data.not.authentic',
                    array(
                        'passwordId' => $id
                    )
                ));
            }

            $AuthKeyPair        = new AuthKeyPair($row['keyPairId']);
            $passwordKeyParts[] = AsymmetricCrypto::decrypt(
                $row['dataKey'],
                $AuthKeyPair
            );
        }

        // build password key from its parts
        $PasswordKey = new Key(SecretSharing::recoverSecret($passwordKeyParts));

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

        $this->ownerId   = $contentDecrypted['ownerId'];
        $this->ownerType = $contentDecrypted['ownerType'];
        $this->payload   = $contentDecrypted['payload'];
        $this->history   = $contentDecrypted['history'];
    }

    /**
     * Returns password data for frontend view
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
     * Checks if the current password user has a password specific permission
     *
     * @param integer $permission
     * @return bool
     */
    protected function hasPermission($permission)
    {
        switch ($permission) {
            // @todo view von sharedWith abhängig machen
            case self::PERMISSION_VIEW:
            case self::PERMISSION_EDIT:
                return $this->isOwner();
                break;

            case self::PERMISSION_DELETE:
                if ($this->ownerType === 'user') {
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
                if ($this->ownerType === 'user') {
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
            case 'user':
                return (int)$this->ownerId === (int)$this->User->getId();
                break;

            case 'group':
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
        // @todo eigenen fehlercode einfügen
        throw new QUI\Exception(array(
            'pcsg/grouppasswordmanager',
            'exception.password.permission.denied'
        ));
    }
}