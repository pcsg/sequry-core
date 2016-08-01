<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Constants\Permissions
 */

namespace Pcsg\GroupPasswordManager\Constants;

/**
 * Authentication Class for crypto data and crypto users/groups
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Permissions
{
    const PASSWORDS_CREATE = 'pcsg.gpm.cryptodata.create';
    const PASSWORDS_EDIT   = 'pcsg.gpm.cryptodata.edit';
    const PASSWORDS_DELETE = 'pcsg.gpm.cryptodata.delete';
    const PASSWORDS_SHARE  = 'pcsg.gpm.cryptodata.share';
}