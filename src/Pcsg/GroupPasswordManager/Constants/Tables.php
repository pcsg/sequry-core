<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Constants\Tables
 */

namespace Pcsg\GroupPasswordManager\Constants;

use QUI;

/**
 * Password Manager table constants
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Tables
{
    const AUTH_PLUGINS     = 'pcsg_gpm_auth_plugins';
    const SECURITY_CLASSES = 'pcsg_gpm_security_classes';
    const SECURITY_TO_AUTH = 'pcsg_gpm_security_classes_to_auth_plugins';

    const KEYPAIRS_USER  = 'pcsg_gpm_user_keypairs';
    const KEYPAIRS_GROUP = 'pcsg_gpm_group_keypairs';

    const USER_TO_PASSWORDS = 'pcsg_gpm_user_data_access';
    const PASSWORDS         = 'pcsg_gpm_password_data';

    const USER_TO_GROUPS     = 'pcsg_gpm_user_group_access';
    const GROUP_TO_PASSWORDS = 'pcsg_gpm_group_data_access';
    const GROUP_ADMINS       = 'pcsg_gpm_group_admins';

    const RECOVERY = 'pcsg_gpm_recovery';

    const USER_TO_PASSWORDS_META = 'pcsg_gpm_user_data_access_meta';

    /**
     * Auth plugins table
     *
     * @return string
     */
    public static function authPlugins()
    {
        return QUI::getDBTableName(self::AUTH_PLUGINS);
    }

    /**
     * Security classes table
     *
     * @return string
     */
    public static function securityClasses()
    {
        return QUI::getDBTableName(self::SECURITY_CLASSES);
    }

    /**
     * Get relation table (Security class <-> Auth plugin)
     *
     * @return string
     */
    public static function securityClassesToAuthPlugins()
    {
        return QUI::getDBTableName(self::SECURITY_TO_AUTH);
    }

    /**
     * Get users key pair table
     *
     * @return string
     */
    public static function keyPairsUser()
    {
        return QUI::getDBTableName(self::KEYPAIRS_USER);
    }

    /**
     * Get groups key pair table
     *
     * @return string
     */
    public static function keyPairsGroup()
    {
        return QUI::getDBTableName(self::KEYPAIRS_GROUP);
    }

    /**
     * Get relation table (users <-> passwords)
     *
     * @return string
     */
    public static function usersToPasswords()
    {
        return QUI::getDBTableName(self::USER_TO_PASSWORDS);
    }

    /**
     * Get password table
     *
     * @return string
     */
    public static function passwords()
    {
        return QUI::getDBTableName(self::PASSWORDS);
    }

    /**
     * Get relation table (users <-> groups)
     *
     * @return string
     */
    public static function usersToGroups()
    {
        return QUI::getDBTableName(self::USER_TO_GROUPS);
    }

    /**
     * Get relation table (groups <-> passwords)
     *
     * @return string
     */
    public static function groupsToPasswords()
    {
        return QUI::getDBTableName(self::GROUP_TO_PASSWORDS);
    }

    /**
     * Get recovery code table
     *
     * @return string
     */
    public static function recovery()
    {
        return QUI::getDBTableName(self::RECOVERY);
    }

    /**
     * Get relation table (users <-> user password meta data)
     *
     * @return string
     */
    public static function usersToPasswordMeta()
    {
        return QUI::getDBTableName(self::USER_TO_PASSWORDS_META);
    }

    /**
     * Get group admins table
     *
     * @return string
     */
    public static function groupAdmins()
    {
        return QUI::getDBTableName(self::GROUP_ADMINS);
    }
}
