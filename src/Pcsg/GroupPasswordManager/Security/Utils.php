<?php

namespace Pcsg\GroupPasswordManager\Security;

use Pcsg\GroupPasswordManager\Constants\Crypto;
use QUI;

/**
 * This class provides general security function the pcsg/grouppasswordmanager module
 */
class Utils
{
    /**
     * Strip the module version from a string (if appended) and return original string
     *
     * @param string $str
     * @return string
     */
    public static function stripModuleVersionString($str)
    {
        if (mb_substr($str, -3) === '$$$') {
            $str = mb_substr($str, 0, -Crypto::VERSION_LENGTH, '8bit');
        }

        return $str;
    }

    /**
     * Get special version string fro a cryptomodule
     *
     * This string is appended to encrypted/hashed/encoded string to identify
     * the package version and crypto module that was used to generate such string.
     *
     * @param string $module - internal class name of used crypto module
     * @return string - padded to 30 characters
     */
    public static function getCryptoModuleVersionString($module)
    {
        $str = '';

        $str .= self::getPackageVersion();
        $str .= '|' . $module;

        return self::mb_str_pad($str, Crypto::VERSION_LENGTH, '$');
    }

    /**
     * str_pad (multi-byte safe)
     *
     * @param string $str
     * @param int $pad_len
     * @param string $pad_str
     * @param int $dir
     * @param null $encoding
     * @return string
     */
    function mb_str_pad($str, $pad_len, $pad_str = ' ', $dir = STR_PAD_RIGHT, $encoding = null)
    {
        $encoding = $encoding === null ? mb_internal_encoding() : $encoding;

        $padBefore = $dir === STR_PAD_BOTH || $dir === STR_PAD_LEFT;
        $padAfter  = $dir === STR_PAD_BOTH || $dir === STR_PAD_RIGHT;

        $pad_len -= mb_strlen($str, $encoding);

        $targetLen      = $padBefore && $padAfter ? $pad_len / 2 : $pad_len;
        $strToRepeatLen = mb_strlen($pad_str, $encoding);
        $repeatTimes    = ceil($targetLen / $strToRepeatLen);
        $repeatedString = str_repeat($pad_str, max(0, $repeatTimes)); // safe if used with valid utf-8 strings
        $before         = $padBefore ? mb_substr($repeatedString, 0, floor($targetLen), $encoding) : '';
        $after          = $padAfter ? mb_substr($repeatedString, 0, ceil($targetLen), $encoding) : '';

        return $before . $str . $after;
    }

    /**
     * Get version of pcsg/grouppasswordmanager package
     *
     * @return string
     */
    public static function getPackageVersion()
    {
        $composerData = QUI::getPackage('pcsg/grouppasswordmanager')->getComposerData();

        if (isset($composerData['version'])) {
            return $composerData['version'];
        }

        return '';
    }

    /**
     * Get system authentication key for key pairs
     *
     * @return string
     * @throws \QUI\Exception
     */
    public static function getSystemKeyPairAuthKey()
    {
        $keyFile = ETC_DIR . 'plugins/pcsg/gpm_auth_keypairs.key';

        // if key does not exit -> create
        if (!file_exists($keyFile)) {
            $RandomKey = SymmetricCrypto::generateKey();
            file_put_contents($keyFile, $RandomKey->getValue());

            if (!file_exists($keyFile)) {
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.system.auth.key.file.not.found'
                ), 404);
            }
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

        // if key does not exit -> create
        if (!file_exists($keyFile)) {
            $RandomKey = SymmetricCrypto::generateKey();
            file_put_contents($keyFile, $RandomKey->getValue());

            if (!file_exists($keyFile)) {
                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.system.auth.key.file.not.found'
                ), 404);
            }
        }

        return file_get_contents($keyFile);
    }
}
