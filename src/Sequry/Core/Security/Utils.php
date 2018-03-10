<?php

namespace Sequry\Core\Security;

use Sequry\Core\Constants\Crypto;
use Sequry\Core\Security\Keys\Key;
use QUI\Utils\Security\Orthos;
use QUI;

/**
 * This class provides general security function the sequry/core module
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
        if (mb_substr($str, -3, null, '8bit') === '$$$') {
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
    public static function mb_str_pad($str, $pad_len, $pad_str = ' ', $dir = STR_PAD_RIGHT, $encoding = null)
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
     * Get version of sequry/core package
     *
     * @return string
     */
    public static function getPackageVersion()
    {
        $composerData = QUI::getPackage('sequry/core')->getComposerData();

        if (isset($composerData['version'])) {
            return $composerData['version'];
        }

        return '';
    }

    /**
     * Get system authentication key for key pairs
     *
     * @return Key
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
                    'sequry/core',
                    'exception.system.auth.key.file.not.found'
                ), 404);
            }
        }

        return new Key(new HiddenString(file_get_contents($keyFile)));
    }

    /**
     * Get system authentication key for passwords
     *
     * @return Key
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
                    'sequry/core',
                    'exception.system.auth.key.file.not.found'
                ), 404);
            }
        }

        return new Key(new HiddenString(file_get_contents($keyFile)));
    }

    /**
     * Clear array of potentially unsafe code
     *
     * @param array $data
     * @return array - cleared data
     */
    public static function clearArray($data)
    {
        if (!is_array($data)) {
            return array();
        }

        $cleanData = array();

        foreach ($data as $key => $str) {
            if (is_array($data[$key])) {
                $cleanData[$key] = self::clearArray($data[$key]);
                continue;
            }

            $cleanData[$key] = self::clear($str);
        }

        return $cleanData;
    }

    /**
     * Clear string
     *
     * @param string $str
     * @return string - cleared string
     */
    public static function clear($str)
    {
        $str = Orthos::removeHTML($str);
        $str = Orthos::clearPath($str);
//        $str = Orthos::clearFormRequest($str);

        $str = htmlspecialchars($str);

        return $str;
    }

    /**
     * Generate a random password
     *
     * @return string
     */
    public static function generatePassword()
    {
        $passwordParts = array();

        // 3 to 5 numbers
        for ($i = 0, $len = random_int(3,5); $i < $len; $i++) {
            $passwordParts[] = random_int(0,9);
        }

        // 3 to 5 special characters
        $special = array('-', '_', '$', '@', '?');

        for ($i = 0, $len = random_int(3,5); $i < $len; $i++) {
            $passwordParts[] = $special[random_int(0, (count($special)-1))];
        }

        // 4 to 10 letters
        $letters = array(
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n',
            'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N',
            'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'
        );

        for ($i = 0, $len = random_int(4,10); $i < $len; $i++) {
            $passwordParts[] = $letters[random_int(0, (count($letters)-1))];
        }

        // shuffle parts
        for ($i = 0, $len = random_int(500,1000); $i < $len; $i++) {
            shuffle($passwordParts);
        }

        return implode('', $passwordParts);
    }

    /**
     * Perform a json_decode and catch all errors. Returns an array in every case.
     *
     * @param string $arrayData - Data to be decoded
     * @return array
     */
    public static function saveJsonDecode($arrayData) {
        if (!is_string($arrayData)) {
            return array();
        }

        $array = json_decode($arrayData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array();
        }

        return $array;
    }

    /**
     * Check if a string is ins JSON format
     *
     * @param string $str
     * @return bool
     */
    public static function isJson($str)
    {
        $str = json_decode($str, true);
        return json_last_error() === JSON_ERROR_NONE && is_array($str);
    }
}
