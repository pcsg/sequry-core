<?php

/**
 * This file contains \QUI\Kapitalschutz\Events
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use Pcsg\GroupPasswordManager\Security\Handler\Passwords;
use QUI;
use Pcsg\GroupPasswordManager\Security\Handler\Authentication;

/**
 * Class Events
 *
 * @package kapitalschutz/kanzlei
 * @author www.pcsg.de (Patrick Müller)
 */
class Events
{
    /**
     * on event : onPackageSetup
     */
    public static function onPackageSetup()
    {
        Authentication::loadAuthPlugins();
    }

    /**
     * on event: onUserSaveBegin
     *
     * Checks if user groups can be changed
     *
     * @param QUI\Users\User $User
     */
    public static function onUserSaveBegin($User)
    {
        $EditUser   = QUI::getUserBySession();
        $CryptoUser = CryptoActors::getCryptoUser($User->getId());

        // get groups of user before edit
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'usergroup'
            ),
            'from'   => 'users',
            'where'  => array(
                'id' => $User->getId()
            )
        ));

        $groupsBefore = array();

        if (!empty($result)) {
            $groupsBefore = trim($result[0]['usergroup'], ',');
            $groupsBefore = explode(',', $groupsBefore);
        }

        $groupsNow   = $User->getGroups(false);
        $groupsAdded = array_diff($groupsNow, $groupsBefore);

        // check groups that are to be removed
        $groupsRemoved = array_diff($groupsBefore, $groupsNow);

        // get all crypto groups of the QUIQQER groups that are to be removed
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'groupId'
            ),
            'from'   => Tables::KEYPAIRS_GROUP,
            'where'  => array(
                'groupId' => array(
                    'type'  => 'IN',
                    'value' => $groupsRemoved
                )
            )
        ));

        $removeAccess = array();

        foreach ($result as $row) {
            $removeAccess[] = $row['groupId'];
        }


        // @todo prüfen, ob benutzer der letzte der gruppe ist
        // @todo prüfen, ob gruppe passwörter zugeordnet hat

        foreach ($removeAccess as $groupId) {
            $CryptoGroup = CryptoActors::getCryptoGroup($groupId);

            $CryptoGroup->removeCryptoUser($CryptoUser);
        }


//            if ($info['access'] === false) {
//                QUI::getMessagesHandler()->addAttention(
//                    QUI::getLocale()->get(
//                        'pcsg/grouppasswordmanager',
//                        'attention.events.user.save.cannot.remove.group', array(
//                            'userId'  => $User->getId(),
//                            'groupId' => $groupId
//                        )
//                    )
//                );
//
//                $groupsNow[] = $groupId;
//
//                continue;
//            }
//
//            // remove user access from all passwords
//            foreach ($info['passwordIds'] as $passwordId) {
//                try {
//                    $Password = Passwords::get($passwordId);
//                    $Password->removePasswordAccess($User, $groupId);
//                } catch (\Exception $Exception) {
//                    QUI\System\Log::addError(
//                        'PasswordManager Event: onUserSaveBegin -> cannot remove password access: ' . $Exception->getMessage()
//                    );
//
//                    QUI::getMessagesHandler()->addAttention(
//                        QUI::getLocale()->get(
//                            'pcsg/grouppasswordmanager',
//                            'error.events.user.save.remove.group', array(
//                                'passwordId' => $passwordId,
//                                'userId'     => $User->getId(),
//                                'groupId'    => $groupId
//                            )
//                        )
//                    );
//                }
//            }
        }

        if (empty($groupsAdded)) {
            $User->setGroups($groupsNow);
            return;
        }

        // get all passwords the added groups have access to
        \QUI\System\Log::writeRecursive($groupsAdded);

        $User->setGroups($groupsNow);
    }
}