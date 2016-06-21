<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Password
 */

namespace Pcsg\GroupPasswordManager\Constants;

use QUI;

/**
 * Authentication Class for crypto data and crypto users
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Tables
{
    const AUTH_PLUGINS      = 'pcsg_gpm_auth_plugins';
    const SECURITY_CLASSES  = 'pcsg_gpm_security_classes';
    const SECURITY_TO_AUTH  = 'pcsg_gpm_security_classes_to_auth_plugins';

    const KEYPAIRS          = 'pcsg_gpm_keypairs';

    const USER_TO_PASSWORDS = 'pcsg_gpm_user_data_access';
    const PASSWORDS         = 'pcsg_gpm_password_data';
}