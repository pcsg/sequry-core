<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Password
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use QUI;

/**
 * Manager Class for passwords and crypto users
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Manager
{
    // Password tables
    const TBL_CRYPTODATA = 'pcsg_gpm_data';

    // User tables
    const TBL_USERS = 'pcsg_gpm_users';
    const TBL_USER_CRYPTODATA = 'pcsg_gpm_user_data_access';
    const TBL_USER_GROUPS = 'pcsg_gpm_user_groups';

    // Group tables
    const TBL_GROUPS = 'pcsg_gpm_groups';
    const TBL_GROUP_CRYPTODATA = 'pcsg_gpm_group_data_access';

    // Existing authentification security levels (may be extended some time in the future)
    protected static $_authLevels = array(
        'default' => true,
        'mfa' => true
    );

    /**
     * Create a new encrypted CryptoData object
     *
     * @param String $title - Password title
     * @param String $description - Short description for the password
     * @param Array|String $payload - Payload (sensitive data)
     * @param String $authLevel - Authentication level used (determines authentification plugins for key encryption) [default: "default"]
     * @param QUI\Users\User $Owner (optional) - Owner of the password [default: Session User]
     * @return CryptoData
     * @throws QUI\Exception
     */
    public static function createCryptoData(
        $title,
        $description,
        $payload,
        $authLevel = 'default',
        $Owner = null
    ) {
        if (is_null($Owner)) {
            $Owner = QUI::getUserBySession();
        }

        $title       = trim($title);
        $description = trim($description);

        if (empty($title)
            || empty($description)
        ) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'pcsg/grouppasswordmanager',
                    'exception.manager.create.password.missing.title.description'
                )
            );
        }

        if (!isset(self::$_authLevels[$authLevel])) {
            // @todo error code
            throw new QUI\Exception('Unknown auth level.');
        }

        // encrypt data
        $cryptoPayload = array(
            'payload' => $payload,
            'ownerId' => $Owner->getId(),
            'authLevel' => $authLevel
        );

        $cryptoPayload = json_encode($cryptoPayload);

        // Generate random secure key (this will only happen once)
        $cryptoKey = SymmetricCrypto::generateKey();

        // Use authenticated encryption
        $cipherText = SymmetricCrypto::encrypt($cryptoPayload, $cryptoKey);

        QUI::getDataBase()->insert(
            self::TBL_CRYPTODATA,
            array(
                'title' => $title,
                'description' => $description,
                'cryptoData' => $cipherText
            )
        );

        $CryptoData = new CryptoData(
            QUI::getDataBase()->getPDO()->lastInsertId(),
            $cryptoKey
        );

        // add cryptodata right for owner
        $CryptoData->addUser(new CryptoUser($Owner->getId()));

        return $CryptoData;
    }

    /**
     * @param QUI\Groups\Group $Group - The QUIQQER Group
     * @param QUI\Users\User $Owner (optional) - Owner of the group [default: Session User]
     * @return CryptoGroup
     */
    public static function createCryptoGroup($Group, $Owner = null)
    {

    }

    /**
     * Creates a CryptoUser with public/private key pair related to a QUIQQER User
     *
     * @param QUI\Users\User $User - Related QUIQQER User
     * @return CryptoUser
     */
    public static function createCryptoUser(QUI\Users\User $User)
    {
        $CrpyotUser = new CryptoUser($User->getId());
        $CrpyotUser->generateKeyPair();

        return $CrpyotUser;
    }
}