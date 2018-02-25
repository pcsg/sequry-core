<?php
//
//namespace Sequry\Core\Security\Modules\SecretSharing;
//
//use Sequry\Core\Security\Interfaces\ISecretSharing;
//use phpseclib\Crypt\Hash as HMACClass;
//
///**
// * This class provides a secret splitting API for the sequry/core module
// *
// * Splits a secret into multiple parts - ALL parts are needed to revocer the secret
// *
// * @author PCSG (Patrick Müller)
// */
//class SimpleSplit implements ISecretSharing
//{
//    /**
//     * Splits a secret into multiple parts
//     *
//     * @param string $secret
//     * @param integer $parts - number of parts the secret is split into
//     * @param integer $required - number of minimum required parts to recover the secret
//     * @return array
//     */
//    public static function splitSecret($secret, $parts, $required)
//    {
//        // get byte length of key
//        $keyBytes  = mb_strlen($secret, '8bit');
//        $parts     = (int)$parts;
//        $splitKeys = array();
//
//        if ($parts < 2) {
//            $splitKeys[] = $secret;
//            return $splitKeys;
//        }
//
//        $value = $secret;
//
//        for ($i = 1; $i < $parts; $i++) {
//            // generate random bytes in key size
//            $rndBytes = \Sodium\randombytes_buf($keyBytes);
//            $newPart  = $value ^ $rndBytes;
//
//            $splitKeys[] = $rndBytes;
//            $value       = $newPart;
//        }
//
//        $splitKeys[] = $newPart;
//
//        return $splitKeys;
//    }
//
//    /**
//     * Recover a secret from parts
//     *
//     * @param array $parts - the parts to recover the secret from
//     * @return string - the secret
//     */
//    public static function recoverSecret($parts)
//    {
//        if (count($parts) < 2) {
//            return current($parts);
//        }
//
//        // start with appropiate length of 0-bytes (assumes all parts are of the same byte-length)
//        $key = \str_repeat("\x00", mb_strlen(current($parts), '8bit'));
//
//        foreach ($parts as $part) {
//            $key ^= $part;
//        }
//
//        return $key;
//    }
//}
