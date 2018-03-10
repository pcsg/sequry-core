<?php

/**
 * This file contains \Sequry\Core\Constants\Permissions
 */

namespace Sequry\Core\Constants;

/**
 * Authentication Class for crypto data and crypto users/groups
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Permissions
{
    const PASSWORDS_CREATE       = 'gpm.cryptodata.create';
    const PASSWORDS_DELETE_GROUP = 'gpm.cryptodata.delete_group';
    const PASSWORDS_SHARE        = 'gpm.cryptodata.share';
    const PASSWORDS_SHARE_GROUP  = 'gpm.cryptodata.share_group';

    const GROUP_CREATE        = 'gpm.cryptogroup.create';
    const GROUP_MANAGE_ADMINS = 'gpm.cryptogroup.manage_admins';

    const SECURITY_CLASS_EDIT = 'gpm.securityclass.edit';
}
