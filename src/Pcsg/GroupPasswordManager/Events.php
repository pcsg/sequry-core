<?php

/**
 * This file contains \QUI\Kapitalschutz\Events
 */

namespace Pcsg\GroupPasswordManager;

use QUI\Package\Package;
use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
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
     * If warning on user delete should be triggered or not
     *
     * @var bool
     */
    public static $triggerUserDeleteConfirm = true;

    /**
     * If warning on group delete should be triggered or not
     *
     * @var bool
     */
    public static $triggerGroupDeleteConfirm = true;

    /**
     * on event : onPackageSetup
     *
     * @param Package $Package
     * @return void
     */
    public static function onPackageSetup(Package $Package)
    {
        if ($Package->getName() !== 'pcsg/grouppasswordmanager') {
            return;
        }

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

        if (!empty($groupsRemoved)) {
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

            $groupsHandled = array();

            foreach ($result as $row) {
                if (isset($groupsHandled[$row['groupId']])) {
                    continue;
                }

                $CryptoGroup                    = CryptoActors::getCryptoGroup($row['groupId']);
                $groupsHandled[$row['groupId']] = true;

                try {
                    $CryptoGroup->removeCryptoUser($CryptoUser);
                } catch (\Exception $Exception) {
                    QUI::getMessagesHandler()->addAttention(
                        QUI::getLocale()->get(
                            'pcsg/grouppasswordmanager',
                            'attention.events.onusersavebegin.remove.user.error',
                            array(
                                'userId'    => $User->getId(),
                                'userName'  => $User->getUsername(),
                                'groupId'   => $CryptoGroup->getId(),
                                'groupName' => $CryptoGroup->getAttribute('name'),
                                'error'     => $Exception->getMessage()
                            )
                        )
                    );

                    $groupsNow[] = $CryptoGroup->getId();
                }
            }
        }

        if (!empty($groupsAdded)) {
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

            $groupsHandled = array();

            foreach ($result as $row) {
                if (isset($groupsHandled[$row['groupId']])) {
                    continue;
                }

                $CryptoGroup                    = CryptoActors::getCryptoGroup($row['groupId']);
                $groupsHandled[$row['groupId']] = true;

                try {
                    $CryptoGroup->addCryptoUser($CryptoUser);
                } catch (\Exception $Exception) {
                    QUI::getMessagesHandler()->addAttention(
                        QUI::getLocale()->get(
                            'pcsg/grouppasswordmanager',
                            'attention.events.onusersavebegin.add.user.error',
                            array(
                                'userId'    => $User->getId(),
                                'userName'  => $User->getUsername(),
                                'groupId'   => $CryptoGroup->getId(),
                                'groupName' => $CryptoGroup->getAttribute('name'),
                                'error'     => $Exception->getMessage()
                            )
                        )
                    );

                    $groupKey = array_search($CryptoGroup->getId(), $groupsNow);
                    unset($groupsNow[$groupKey]);
                }
            }
        }

        if (empty($groupsNow)) {
            $User->clearGroups();
            return;
        }

        $User->setGroups($groupsNow);
    }

    /**
     * event: on user delete
     *
     * Throws an exception so the standard user deletion progress is immediately aborted
     * and an own procedure can be called from the frontend
     *
     * @return void
     * @param QUI\Users\User $User
     * @throws QUI\Exception
     */
    public static function onUserDelete($User)
    {
        if (!self::$triggerUserDeleteConfirm) {
            return;
        }

        $SessionUser = QUI::getUserBySession();

        if ((int)$User->getId() !== (int)$SessionUser->getId()
            && !$SessionUser->isSU()
        ) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.events.user.delete.no.permission'
            ));
        }

        QUI::getAjax()->triggerGlobalJavaScriptCallback(
            'userDeleteConfirm',
            array(
                'userId'   => $User->getId(),
                'userName' => $User->getUsername()
            )
        );

        throw new QUI\Exception(array(
            'pcsg/grouppasswordmanager',
            'exception.events.user.delete.info'
        ));
    }

    /**
     * event: on group delete
     *
     * Throws an exception so the standard group deletion progress is immediately aborted
     * and an own procedure can be called from the frontend
     *
     * @return void
     * @param QUI\Groups\Group $Group
     * @throws QUI\Exception
     */
    public static function onGroupDelete($Group)
    {
        if (!self::$triggerGroupDeleteConfirm) {
            return;
        }

        $SessionUser = QUI::getUserBySession();

        if (!$SessionUser->isSU()) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.events.group.delete.no.permission'
            ));
        }

        QUI::getAjax()->triggerGlobalJavaScriptCallback(
            'groupDeleteConfirm',
            array(
                'groupId'   => $Group->getId(),
                'groupName' => $Group->getAttribute('name')
            )
        );

        throw new QUI\Exception(array(
            'pcsg/grouppasswordmanager',
            'exception.events.group.delete.info'
        ));
    }

    /**
     * event: onAdminLoad
     *
     * Load css files into admin header
     */
    public static function onAdminLoad()
    {
        $cssFile = URL_OPT_DIR . 'pcsg/grouppasswordmanager/bin/style.css';
        echo '<link href="' . $cssFile . '" rel="stylesheet" type="text/css"/>';
    }

    /**
     * event: onAdminLoadFooter
     *
     * Load javascript code into admin footer
     */
    public static function onAdminLoadFooter()
    {
        $jsFile = URL_OPT_DIR . 'pcsg/grouppasswordmanager/bin/onAdminLoadFooter.js';
        echo '<script src="' . $jsFile . '"></script>';
    }
}
