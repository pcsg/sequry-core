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
        $EditUser = QUI::getUserBySession();

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

        // get all passwords the removed groups have access to
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'dataId',
                'groupId'
            ),
            'from'   => Tables::USER_TO_PASSWORDS,
            'where'  => array(
                'groupId' => array(
                    'type'  => 'IN',
                    'value' => $groupsRemoved
                )
            )
        ));

        $removeAccess = array();

        foreach ($result as $row) {
            $passwordId = $row['dataId'];
            $groupId    = $row['groupId'];

            if (!isset($removeAccess[$groupId])) {
                $removeAccess[$groupId] = array(
                    'access'      => true,
                    'passwordIds' => array()
                );
            }

            if ($removeAccess[$groupId]['access'] === false) {
                continue;
            }

            if (Passwords::hasPasswordAccess($EditUser, $passwordId)) {
                $removeAccess[$groupId]['passwordIds'][] = $passwordId;
            } else {
                $removeAccess[$groupId]['access'] = false;
            }
        }

        foreach ($removeAccess as $groupId => $info) {
            if ($info['access'] === false) {
                QUI::getMessagesHandler()->addAttention(
                    QUI::getLocale()->get(
                        'pcsg/grouppasswordmanager',
                        'attention.events.user.save.cannot.remove.group', array(
                            'userId'  => $User->getId(),
                            'groupId' => $groupId
                        )
                    )
                );

                $groupsNow[] = $groupId;

                continue;
            }

            // remove user access from all passwords
            foreach ($info['passwordIds'] as $passwordId) {
                try {
                    $Password = Passwords::get($passwordId);
                    $Password->removePasswordAccess($User, $groupId);
                } catch (\Exception $Exception) {
                    QUI\System\Log::addError(
                        'PasswordManager Event: onUserSaveBegin -> cannot remove password access: ' . $Exception->getMessage()
                    );

                    QUI::getMessagesHandler()->addAttention(
                        QUI::getLocale()->get(
                            'pcsg/grouppasswordmanager',
                            'error.events.user.save.remove.group', array(
                                'passwordId' => $passwordId,
                                'userId'     => $User->getId(),
                                'groupId'    => $groupId
                            )
                        )
                    );
                }
            }
        }

        if (empty($groupsAdded)) {
            return;
        }

        // get all passwords the added groups have access to
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'dataId',
                'groupId'
            ),
            'from'   => Tables::USER_TO_PASSWORDS,
            'where'  => array(
                'groupId' => array(
                    'type'  => 'IN',
                    'value' => $groupsAdded
                )
            )
        ));

        $addAccess = array();

        foreach ($result as $row) {
            $passwordId = $row['dataId'];
            $groupId    = $row['groupId'];

            if (!isset($addAccess[$groupId])) {
                $addAccess[$groupId] = array(
                    'access'      => true,
                    'passwordIds' => array()
                );
            }

            if ($addAccess[$groupId]['access'] === false) {
                continue;
            }

            if (Passwords::hasPasswordAccess($EditUser, $passwordId)) {
                $addAccess[$groupId]['passwordIds'][] = $passwordId;
            } else {
                $addAccess[$groupId]['access'] = false;
            }
        }

        foreach ($addAccess as $groupId => $info) {
            if ($info['access'] === false) {
                QUI::getMessagesHandler()->addAttention(
                    QUI::getLocale()->get(
                        'pcsg/grouppasswordmanager',
                        'attention.events.user.save.cannot.add.group', array(
                            'userId'  => $User->getId(),
                            'groupId' => $groupId
                        )
                    )
                );

                $key = array_search($groupId, $groupsNow);
                unset($groupsNow[$key]);

                continue;
            }

            // remove user access from all passwords
            foreach ($info['passwordIds'] as $passwordId) {
                try {
                    $Password = Passwords::get($passwordId);
                    $Password->createPasswordAccess(
                        CryptoActors::getCryptoUser($User->getId()),
                        $groupId
                    );
                } catch (\Exception $Exception) {
                    QUI\System\Log::addError(
                        QUI::getLocale()->get(
                            'pcsg/grouppasswordmanager',
                            'error.events.user.save.add.group', array(
                                'passwordId' => $passwordId,
                                'userId'     => $User->getId(),
                                'groupId'    => $groupId
                            )
                        )
                    );
                }
            }
        }

        $User->setGroups($groupsNow);
    }
}