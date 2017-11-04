<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Security\Handler\Recovery
 */

namespace Pcsg\GroupPasswordManager\Security\Handler;

use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Actors\CryptoUser;
use Pcsg\GroupPasswordManager\Exception\Exception;
use Pcsg\GroupPasswordManager\Security\Authentication\Plugin;
use Pcsg\GroupPasswordManager\Security\KDF;
use Pcsg\GroupPasswordManager\Security\MAC;
use Pcsg\GroupPasswordManager\Security\Random;
use Pcsg\GroupPasswordManager\Security\SymmetricCrypto;
use Pcsg\GroupPasswordManager\Security\Utils;
use QUI;
use Pcsg\GroupPasswordManager\Security\HiddenString;

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
     * @param HiddenString $information - authentication information for plugin
     * @param CryptoUser $CryptoUser (optional) - the user the recovery data is created for;
     * if omitted uses session user
     *
     * @return array - recovery code information
     *
     * @throws QUI\Exception
     */
    public static function createEntry($AuthPlugin, HiddenString $information, $CryptoUser = null)
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

        $RecoveryKey = KDF::createKey(
            new HiddenString($recoveryCode),
            $recoverySalt
        );

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

        $MAC = MAC::create(
            new HiddenString(implode('', $MACData)),
            Utils::getSystemPasswordAuthKey()
        );

        // delete previous entry (if it exists)
        QUI::getDataBase()->delete(
            Tables::recovery(),
            array(
                'userId'       => $userId,
                'authPluginId' => $authPluginId,
            )
        );

        // insert new entry
        QUI::getDataBase()->insert(
            Tables::recovery(),
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
            'userName'        => $CryptoUser->getName(),
            'authPluginId'    => $AuthPlugin->getId(),
            'authPluginTitle' => $AuthPlugin->getAttribute('title'),
            'recoveryCodeId'  => QUI::getDataBase()->getPDO()->lastInsertId(),
            'recoveryCode'    => $recoveryCode,
            'date'            => date('d.m.Y')
        );

        // save in session
        QUI::getSession()->set(
            'pcsg_gpm_recovery_code_' . $CryptoUser->getId() . '_' . $AuthPlugin->getId(),
            json_encode($recoveryCodeData)
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
            'from'   => Tables::recovery(),
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
     * @param HiddenString $recoveryCode - Recovery Code (was generated upon authentication plugin registration)
     * @param HiddenString $recoveryToken - Recovery Token (was sent via mail)
     * @param CryptoUser $CryptoUser (optional) - the user the recovery data is created for;
     * if omitted uses session user
     *
     * @return string - recovered secret
     *
     * @throws QUI\Exception
     */
    public static function recoverEntry(
        $AuthPlugin,
        HiddenString $recoveryCode,
        HiddenString $recoveryToken,
        $CryptoUser = null
    ) {
        if (is_null($CryptoUser)) {
            $CryptoUser = CryptoActors::getCryptoUser();
        }

        // get data
        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::recovery(),
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

        $MACActual = MAC::create(
            new HiddenString(implode('', $MACData)),
            Utils::getSystemPasswordAuthKey()
        );

        if (!MAC::compare($MACActual, $MACExpected)) {
            QUI\System\Log::addCritical(
                'Recovery :: recoverSecret() #' . $data['id'] . ' possibly altered. MAC mismatch!'
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.recovery.recover.entry.mac.mismatch'
            ));
        }

        // check token
        try {
            $realToken = SymmetricCrypto::decrypt(
                $data['recoveryToken'],
                Utils::getSystemPasswordAuthKey()
            );
        } catch (\Exception $Exception) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.recovery.wrong_token'
            ));
        }

        if ($recoveryToken->getString() !== $realToken->getString()) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.recovery.wrong_token'
            ));
        }

        // decrypt authentication information
        $RecoveryKey = KDF::createKey($recoveryCode, $data['salt']);

        try {
            $recoveredSecret = SymmetricCrypto::decrypt($data['recoveryData'], $RecoveryKey);
        } catch (\Exception $Exception) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.recovery.wrong_code'
            ));
        }

        return $recoveredSecret;
    }

    /**
     * Get recovery data from session!
     *
     * This can be only done ONCE per recovery code. After one retrieval, the data is
     * deleted from the session.
     *
     * @param int $authPluginId - AuthPlugin ID
     * @param CryptoUser $CryptoUser (optional) - if omitted, use session user
     * @return false|array - false if code not in session; recovery code data otherwise
     */
    public static function getRecoveryDataFromSession($authPluginId, $CryptoUser = null)
    {
        if (is_null($CryptoUser)) {
            $CryptoUser = CryptoActors::getCryptoUser();
        }

        $sessionKey = 'pcsg_gpm_recovery_code_' . $CryptoUser->getId() . '_' . $authPluginId;

        $data = QUI::getSession()->get($sessionKey);

        if (empty($data)) {
            return false;
        }

        QUI::getSession()->set($sessionKey, null);

        return json_decode($data, true);
    }

    /**
     * Send recovery token via email
     *
     * @param Plugin $AuthPlugin
     * @return void
     *
     * @throws Exception
     */
    public static function sendRecoveryToken(Plugin $AuthPlugin)
    {
        $User  = QUI::getUserBySession();
        $email = $User->getAttribute('email');

        if (empty($email)) {
            throw new Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.recovery.no_email_address'
            ));
        }

        // Token generation
        $token          = self::generateRecoveryToken();
        $tokenEncrypted = SymmetricCrypto::encrypt(
            new HiddenString($token),
            Utils::getSystemPasswordAuthKey()
        );

        QUI::getDataBase()->update(
            Tables::recovery(),
            array(
                'recoveryToken' => $tokenEncrypted
            ),
            array(
                'id' => self::getRecoveryCodeId($AuthPlugin)
            )
        );

        $Mailer = new QUI\Mail\Mailer();

        $Mailer->setFrom('info@pcsg.de');  // @todo als setting
        $Mailer->setFromName('QUIQQER PasswordManager'); // @todo als setting

        $Mailer->setBody(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'recovery.sendtoken.body',
                array(
                    'authPluginTitle' => $AuthPlugin->getAttribute('title'),
                    'userName'        => $User->getName(),
                    'token'           => $token
                )
            )
        );

        $Mailer->setSubject(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'recovery.sendtoken.subject'
            )
        );

        $Mailer->addRecipient($email);

        try {
            \QUI\System\Log::writeRecursive($token);
//            $Mailer->send();
        } catch (\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());

            throw new Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.recovery.mail_send_error'
            ));
        }
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
            $code .= $chars[random_int(0, $len)];
        }

        return $code;
    }

    /**
     * Generates a human-readable random recovery token
     *
     * @return string
     */
    protected static function generateRecoveryToken()
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

        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, $len)];
        }

        return $code;
    }
}
