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

        foreach ($result as $row) {
            $CryptoGroup = CryptoActors::getCryptoGroup($row['groupId']);

            try {
                $CryptoGroup->removeCryptoUser($CryptoUser);
            } catch (\Exception $Exception) {
                QUI::getMessagesHandler()->addAttention(
                    QUI::getLocale()->get(
                        'pcsg/grouppasswordmanager',
                        'attention.events.onusersavebegin.remove.user.error', array(
                            'userId'  => $User->getId(),
                            'groupId' => $CryptoGroup->getId(),
                            'error'   => $Exception->getMessage()
                        )
                    )
                );

                $groupsNow[] = $CryptoGroup->getId();
            }
        }

        // get all crypto groups of the QUIQQER groups that are to be added
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'groupId'
            ),
            'from'   => Tables::KEYPAIRS_GROUP,
            'where'  => array(
                'groupId' => array(
                    'type'  => 'IN',
                    'value' => $groupsAdded
                )
            )
        ));

        foreach ($result as $row) {
            $CryptoGroup = CryptoActors::getCryptoGroup($row['groupId']);

            try {
                $CryptoGroup->addCryptoUser($CryptoUser);
            } catch (\Exception $Exception) {
                QUI::getMessagesHandler()->addAttention(
                    QUI::getLocale()->get(
                        'pcsg/grouppasswordmanager',
                        'attention.events.onusersavebegin.add.user.error', array(
                            'userId'  => $User->getId(),
                            'groupId' => $CryptoGroup->getId(),
                            'error'   => $Exception->getMessage()
                        )
                    )
                );

                $groupKey = array_search($CryptoGroup->getId(), $groupsNow);
                unset($groupsNow[$groupKey]);
            }
        }

        $User->setGroups($groupsNow);
    }
}