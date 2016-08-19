<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Security\Handler\Recovery
 */

namespace Pcsg\GroupPasswordManager\Security\Handler;

use Pcsg\GroupPasswordManager\Actors\CryptoGroup;
use Pcsg\GroupPasswordManager\Constants\Permissions;
use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Actors\CryptoUser;
use Pcsg\GroupPasswordManager\Password;
use Pcsg\GroupPasswordManager\Security\AsymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Authentication\Plugin;
use Pcsg\GroupPasswordManager\Security\Authentication\SecurityClass;
use Pcsg\GroupPasswordManager\Security\KDF;
use Pcsg\GroupPasswordManager\Security\Keys\AuthKeyPair;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\Random;
use Pcsg\GroupPasswordManager\Security\SecretSharing;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use QUI;
use QUI\Permissions\Permission;

/**
 * Class for for managing recovery of authentication information via second channel
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Recovery
{
    /**
     * Create recovery information for specific authenticataion plugin
     *
     * @param Plugin $AuthPlugin - Authentication Plugin the recovery entry is created for
     * @param mixed $information - authentication information for plugin
     * @param CryptoUser $CryptoUser (optional) - the user the recovery data is created for;
     * if omitted uses session user
     *
     * @return array - recovery code information
     *
     * @throws QUI\Exception
     */
    public static function createEntry($AuthPlugin, $information, $CryptoUser = null)
    {
        if (is_null($CryptoUser)) {
            $CryptoUser = CryptoActors::getCryptoUser();
        }

        try {
            $AuthPlugin->authenticate($information, $CryptoUser);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.recovery.create.entry.wrong.authentication.information'
            ));
        }

        $recoveryCode = self::generateRecoveryCode();
        $recoverySalt = Random::getRandomData();

        $RecoveryKey = KDF::createKey($recoveryCode, $recoverySalt);

        $recoveryData = SymmetricCrypto::encrypt(
            $information,
            $RecoveryKey
        );

        // generate MAC
        $userId       = $CryptoUser->getId();
        $authPluginId = $AuthPlugin->getId();

        $MACData = array(
            $userId,
            $authPluginId,
            $recoveryData,
            $recoverySalt
        );

        $MAC = MAC::create(implode('', $MACData), Utils::getSystemPasswordAuthKey());

        // delete previous entry (if it exists)
        QUI::getDataBase()->delete(
            Tables::RECOVERY,
            array(
                'userId'       => $userId,
                'authPluginId' => $authPluginId,
            )
        );

        // insert new entry
        QUI::getDataBase()->insert(
            Tables::RECOVERY,
            array(
                'userId'       => $userId,
                'authPluginId' => $authPluginId,
                'recoveryData' => $recoveryData,
                'salt'         => $recoverySalt,
                'MAC'          => $MAC
            )
        );

        $recoveryCodeData = array(
            'userId'          => $CryptoUser->getId(),
            'userName'        => $CryptoUser->getUsername(),
            'authPluginId'    => $AuthPlugin->getId(),
            'authPluginTitle' => $AuthPlugin->getAttribute('title'),
            'recoveryCodeId'  => QUI::getDataBase()->getPDO()->lastInsertId(),
            'recoveryCode'    => $recoveryCode,
            'date'            => date('d.m.Y')
        );

        return $recoveryCodeData;
    }

    /**
     * Get ID of recovery code
     *
     * @param Plugin $AuthPlugin
     * @param null $CryptoUser
     * @return false|integer - false if no recovery code found; else recovery code
     */
    public static function getRecoveryCodeId(Plugin $AuthPlugin, $CryptoUser = null)
    {
        if (is_null($CryptoUser)) {
            $CryptoUser = CryptoActors::getCryptoUser();
        }

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => Tables::RECOVERY,
            'where'  => array(
                'userId'       => $CryptoUser->getId(),
                'authPluginId' => $AuthPlugin->getId()
            )
        ));

        if (empty($result)) {
            return false;
        }

        return (int)$result[0]['id'];
    }

    /**
     * Recover recovery information for specific authenticataion plugin
     *
     * @param Plugin $AuthPlugin - Authentication Plugin the recovery entry is created for
     * @param string $recoveryCode - recovery code
     * @param CryptoUser $CryptoUser (optional) - the user the recovery data is created for;
     * if omitted uses session user
     *
     * @return string - recovered secret
     *
     * @throws QUI\Exception
     */
    public static function recoverEntry($AuthPlugin, $recoveryCode, $CryptoUser = null)
    {
        if (is_null($CryptoUser)) {
            $CryptoUser = CryptoActors::getCryptoUser();
        }

        // get data
        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::RECOVERY,
            'where' => array(
                'userId'       => $CryptoUser->getId(),
                'authPluginId' => $AuthPlugin->getId()
            )
        ));

        if (empty($result)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.recovery.recover.entry.not.found',
                array(
                    'userId'       => $CryptoUser->getId(),
                    'authPluginId' => $AuthPlugin->getId()
                )
            ));
        }

        $data = current($result);

        // check MAC
        $MACExpected = $data['MAC'];

        $MACData = array(
            $data['userId'],
            $data['authPluginId'],
            $data['recoveryData'],
            $data['salt']
        );

        $MACActual = MAC::create(implode('', $MACData), Utils::getSystemPasswordAuthKey());

        if (!MAC::compare($MACActual, $MACExpected)) {
            QUI\System\Log::addCritical(
                'Recovery :: recoverSecret() #' . $data['id'] . ' possibly altered. MAC mismatch!'
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.recovery.recover.entry.mac.mismatch'
            ));
        }

        // decrypt authentication information
        $RecoveryKey = KDF::createKey($recoveryCode, $data['salt']);

        try {
            $recoveredSecret = SymmetricCrypto::decrypt($data['recoveryData'], $RecoveryKey);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.recovery.recover.wrong.code'
            ));
        }

        return $recoveredSecret;
    }

    /**
     * Generates a human-readable random recovery code
     *
     * @return string
     */
    protected static function generateRecoveryCode()
    {
        $chars = array(
            0,
            1,
            2,
            3,
            4,
            5,
            6,
            7,
            8,
            9,
            'A',
            'B',
            'C',
            'D',
            'E',
            'F',
            'G',
            'H',
            'J',
            'K',
            'L',
            'M',
            'N',
            'P',
            'Q',
            'R',
            'S',
            'T',
            'U',
            'V',
            'W',
            'X',
            'Y',
            'Z'
        );

        $code = '';
        $len  = count($chars) - 1;

        for ($i = 0; $i < 25; $i++) {
            $code .= $chars[mt_rand(0, $len)];
        }

        return $code;
    }
}
