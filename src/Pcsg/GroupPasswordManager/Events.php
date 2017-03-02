<?php

/**
 * This file contains \QUI\Kapitalschutz\Events
 */

namespace Pcsg\GroupPasswordManager;

use Pcsg\GpmAuthPassword\AuthPlugin;
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
     * Flag that indicates that users are added to a group via the Group GUI
     * and not the User GUI
     *
     * @var bool
     */
    public static $addUsersViaGroup = false;

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
     * Flag that indicates if the user is authenticated for all
     * relevant security classes that are necessary for adding
     * a user to multiple groups.
     *
     * @var bool
     */
    public static $addGroupsToUserAuthentication = false;

    /**
     * Flag that indicates if the user is authenticated for all
     * relevant security classes that are necessary for adding
     * multiple users to a group.
     *
     * @var bool
     */
    public static $addUsersToGroupAuthentication = false;

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
        self::initialSystemSetup();
    }

    /**
     * on event: onAjaxCallBefore
     *
     * @param string $function - ajax function that is called
     * @param array $params - ajax parameters
     *
     * @throws QUI\Exception
     */
    public static function onAjaxCallBefore($function, $params)
    {
        if ($function !== 'ajax_groups_addUsers') {
            return;
        }

        $CryptoGroup = CryptoActors::getCryptoGroup((int)$params['gid']);
        $userIds     = json_decode($params['userIds'], true);
        $users       = array();

        foreach ($userIds as $userId) {
            $CrpyotUser = CryptoActors::getCryptoUser((int)$userId);
            $users[]    = array(
                'userId'   => $CrpyotUser->getId(),
                'userName' => $CrpyotUser->getName()
            );
        }

        $groupSecurityClassIds = $CryptoGroup->getSecurityClassIds();

        if (empty($groupSecurityClassIds)) {
            self::$addGroupsToUserAuthentication = true;
            return;
        }

        QUI::getAjax()->triggerGlobalJavaScriptCallback(

            'addUsersByGroup',
            array(
                'groupId'          => $CryptoGroup->getId(),
                'groupName'        => $CryptoGroup->getAttribute('name'),
                'securityClassIds' => $CryptoGroup->getSecurityClassIds(),
                'users'            => $users,
                'userIds'          => $userIds
            )
        );

        self::$addUsersViaGroup = true;
    }

    /**
     * on event: onUserSaveBegin
     *
     * Checks if user groups can be changed
     *
     * @param QUI\Users\User $User
     * @throws QUI\Exception
     */
    public static function onUserSaveBegin($User)
    {
        if (self::$addUsersViaGroup) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.events.add.users.to.group.info'
            ));
        }

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

        $groupsNow = $User->getGroups(false);

        if (empty($groupsNow)) {
            return;
        }

        $groupsAdded = array_diff($groupsNow, $groupsBefore);

        if (!empty($groupsAdded)) {
            $securityClassIds = array();
            $groupIds         = array();

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

                if (!self::$addGroupsToUserAuthentication
                    && !self::$addUsersToGroupAuthentication
                ) {
                    $groupSecurityClassIds = $CryptoGroup->getSecurityClassIds();

                    if (empty($groupSecurityClassIds)) {
                        continue;
                    }

                    $securityClassIds = array_merge(
                        $securityClassIds,
                        $groupSecurityClassIds
                    );

                    $groupIds[] = $CryptoGroup->getId();

                    continue;
                }

                try {
                    $CryptoGroup->addCryptoUser($CryptoUser);
                } catch (\Exception $Exception) {
                    QUI::getMessagesHandler()->addAttention(
                        QUI::getLocale()->get(
                            'pcsg/grouppasswordmanager',
                            'attention.events.onusersavebegin.add.user.error',
                            array(
                                'userId'    => $User->getId(),
                                'userName'  => $User->getName(),
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

            if (!self::$addGroupsToUserAuthentication
                && !self::$addUsersToGroupAuthentication
                && !empty($securityClassIds)
            ) {
                QUI::getAjax()->triggerGlobalJavaScriptCallback(
                    'addGroupsToUser',
                    array(
                        'groupIds'         => $groupIds,
                        'securityClassIds' => array_values(array_unique($securityClassIds)),
                        'userId'           => $User->getId()
                    )
                );

                throw new QUI\Exception(array(
                    'pcsg/grouppasswordmanager',
                    'exception.events.add.groups.to.user.info'
                ));
            }
        }

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
                                'userName'  => $User->getName(),
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

        if (QUI::isSystem()) {
            CryptoActors::getCryptoUser($User->getId())->delete();
            return;
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
     *
     * @return void
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
     *
     * @return void
     */
    public static function onAdminLoadFooter()
    {
        $jsFile = URL_OPT_DIR . 'pcsg/grouppasswordmanager/bin/onAdminLoadFooter.js';
        echo '<script src="' . $jsFile . '"></script>';
    }

    /**
     * Performs an initial system setup for first time use of the
     * password manager
     *
     * @return void
     */
    public static function initialSystemSetup()
    {
        // Basic security class
        $securityClasses = Authentication::getSecurityClassesList();

        if (!empty($securityClasses)) {
            return;
        }

        // get ID of basic quiqqer auth plugin
        $defaultPluginId = Authentication::getDefaultAuthPluginId();

        if (empty($defaultPluginId)) {
            return;
        }

        $L  = QUI::getLocale();
        $lg = 'pcsg/grouppasswordmanager';

        Authentication::createSecurityClass(array(
            'title'           => $L->get($lg, 'setup.securityclass.title'),
            'description'     => $L->get($lg, 'setup.securityclass.description'),
            'authPluginIds'   => array($defaultPluginId),
            'requiredFactors' => 1
        ));
    }
}
