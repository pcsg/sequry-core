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
    const PASSWORDS_CREATE = 'gpm.cryptodata.create';
    const PASSWORDS_EDIT   = 'gpm.cryptodata.edit';
    const PASSWORDS_DELETE = 'gpm.cryptodata.delete';
    const PASSWORDS_SHARE  = 'gpm.cryptodata.share';

    const GROUP_EDIT = 'gpm.cryptogroup.edit';

    const SECURITY_CLASS_EDIT = 'gpm.securityclass.edit';
}
