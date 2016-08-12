<?php

namespace Pcsg\GroupPasswordManager\Security;

/**
 * This class provides general security function the pcsg/grouppasswordmanager module
 */
class Utils
{
    /**
     * Get system authentication key for key pairs
     *
     * @return string
     * @throws \QUI\Exception
     */
    public static function getSystemKeyPairAuthKey()
    {
        $keyFile = ETC_DIR . 'plugins/pcsg/gpm_auth_keypairs.key';

        if (!file_exists($keyFile)) {
            throw new \QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.system.auth.key.file.not.found'
            ), 404);
        }

        return file_get_contents($keyFile);
    }

    /**
     * Get system authentication key for passwords
     *
     * @return string
     * @throws \QUI\Exception
     */
    public static function getSystemPasswordAuthKey()
    {
        $keyFile = ETC_DIR . 'plugins/pcsg/gpm_auth_passwords.key';

        if (!file_exists($keyFile)) {
            throw new \QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.system.auth.key.file.not.found'
            ), 404);
        }

        return file_get_contents($keyFile);
    }
}
