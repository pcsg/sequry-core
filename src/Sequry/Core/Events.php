<?php

/**
 * This file contains \Sequry\Core\Events
 */

namespace Sequry\Core;

use Sequry\Core\Actors\CryptoGroup;
use QUI;
use Sequry\Core\Actors\CryptoUser;
use Sequry\Core\Constants\Crypto;
use QUI\Package\Package;
use Sequry\Core\Constants\Tables;
use Sequry\Core\Exception\Exception;
use Sequry\Core\Security\ActionAuthenticator;
use Sequry\Core\Security\Handler\CryptoActors;
use Sequry\Core\Security\Handler\Authentication;
use Sequry\Core\Security\Handler\PasswordLinks;
use Sequry\Core\Security\Authentication\SecurityClass;

/**
 * Class Events
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
     * Flag that indicates that users are added to a group via the Group GUI
     * and is authenticated for the relevant SecurityClasses
     *
     * @var bool
     */
    public static $addUsersViaGroupAuthenticated = false;

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
        if ($Package->getName() !== 'sequry/core') {
            return;
        }

        try {
            Authentication::loadAuthPlugins();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
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
        $users       = [];

        foreach ($userIds as $userId) {
            $CrpyotUser = CryptoActors::getCryptoUser((int)$userId);
            $users[]    = [
                'userId'   => $CrpyotUser->getId(),
                'userName' => $CrpyotUser->getName()
            ];
        }

        $groupSecurityClassIds = $CryptoGroup->getSecurityClassIds();

        if (empty($groupSecurityClassIds)) {
            self::$addGroupsToUserAuthentication = true;
            return;
        }

        $isAuthenticated = true;

        foreach ($groupSecurityClassIds as $securityClassId) {
            $SecurityClass = Authentication::getSecurityClass($securityClassId);

            if (!$SecurityClass->isAuthenticated()) {
                $isAuthenticated = false;
                break;
            }
        }

        self::$addUsersViaGroupAuthenticated = $isAuthenticated;

        QUI::getAjax()->triggerGlobalJavaScriptCallback(
            'addUsersByGroup',
            [
                'groupId'          => $CryptoGroup->getId(),
                'groupName'        => $CryptoGroup->getAttribute('name'),
                'securityClassIds' => $CryptoGroup->getSecurityClassIds(),
                'users'            => $users,
                'userIds'          => $userIds
            ]
        );

        self::$addUsersViaGroup = true;
    }

    /**
     * QUIQQER Event: onUserLogin
     *
     * @param QUI\Users\User $User
     * @return void
     */
    public static function onUserLogin($User)
    {
        try {
            // Automatically overwrite QUIQQER max_session_lifetime
            $maxSessionLifeTime = (int)Settings::getCoreSetting('maxSessionLifetime');
            QUI::$Conf->set('session', 'max_life_time', $maxSessionLifeTime);
            QUI::$Conf->save();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        // generate random 128-bit key and initialization vector
        $commKey = \Sodium\randombytes_buf(16);
        $iv      = \Sodium\randombytes_buf(
            openssl_cipher_iv_length(Crypto::COMMUNICATION_ENCRYPTION_ALGO)
        );

        $data = [
            'key' => bin2hex($commKey),
            'iv'  => bin2hex($iv)
        ];

        QUI::getSession()->set('pcsg-gpm-comm-key', json_encode($data));
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
        // CHECK FOR E-MAIL ADDRESS EDIT
        $result = QUI::getDataBase()->fetch([
            'select' => [
                'email'
            ],
            'from'   => 'users',
            'where'  => [
                'id' => $User->getId()
            ],
            'limit'
        ]);

        if (!empty($result)) {
            $email = $result[0]['email'];

            if ($email !== $User->getAttribute('email')) {
                ActionAuthenticator::checkActionAuthentication(
                    'user_change_mail',
                    [
                        1
                    ]
                );
            }
        }

        // CHECK FOR GROUP EDIT
        if (self::$addUsersViaGroup) {
            if (self::$addUsersViaGroupAuthenticated) {
                throw new Exception([
                    'sequry/core',
                    'exception.events.add.users.to.group.info_authenticated'
                ]);
            }

            throw new Exception([
                'sequry/core',
                'exception.events.add.users.to.group.info'
            ]);
        }

        $CryptoUser = CryptoActors::getCryptoUser($User->getId());

        // get groups of user before edit
        $result = QUI::getDataBase()->fetch([
            'select' => [
                'usergroup'
            ],
            'from'   => 'users',
            'where'  => [
                'id' => $User->getId()
            ]
        ]);

        $groupsBefore = [];

        if (!empty($result)) {
            $groupsBefore = trim($result[0]['usergroup'], ',');
            $groupsBefore = explode(',', $groupsBefore);
        }

        $groupsNow = $User->getGroups(false);

        if (empty($groupsNow)) {
            return;
        }

        $groupsAdded     = array_diff($groupsNow, $groupsBefore);
        $addedAdminUsers = false;

        if (!empty($groupsAdded)) {
            $securityClassIds = [];
            $groupIds         = [];

            // get all crypto groups of the QUIQQER groups that are to be added
            $result = QUI::getDataBase()->fetch([
                'select' => [
                    'groupId'
                ],
                'from'   => Tables::keyPairsGroup(),
                'where'  => [
                    'groupId' => [
                        'type'  => 'IN',
                        'value' => $groupsAdded
                    ]
                ]
            ]);

            $groupsHandled = [];

            foreach ($result as $row) {
                if (isset($groupsHandled[$row['groupId']])) {
                    continue;
                }

                $CryptoGroup                    = CryptoActors::getCryptoGroup($row['groupId']);
                $groupId                        = $CryptoGroup->getId();
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

                    $sessionCache = QUI::getSession()->get('add_adminusers_to_group');

                    if (!empty($sessionCache[$groupId])
                        && in_array($CryptoUser->getId(), $sessionCache[$groupId])) {
                        $CryptoGroup->addAdminUser($CryptoUser, false);
                        $addedAdminUsers = true;

                        QUI::getAjax()->triggerGlobalJavaScriptCallback('refreshGroupAdminPanels');
                    }
                } catch (\Exception $Exception) {
                    QUI::getMessagesHandler()->addAttention(
                        QUI::getLocale()->get(
                            'sequry/core',
                            'attention.events.onusersavebegin.add.user.error',
                            [
                                'userId'    => $User->getId(),
                                'userName'  => $User->getName(),
                                'groupId'   => $CryptoGroup->getId(),
                                'groupName' => $CryptoGroup->getAttribute('name'),
                                'error'     => $Exception->getMessage()
                            ]
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
                    [
                        'groupIds'         => $groupIds,
                        'securityClassIds' => array_values(array_unique($securityClassIds)),
                        'userId'           => $User->getId()
                    ]
                );

                throw new QUI\Exception([
                    'sequry/core',
                    'exception.events.add.groups.to.user.info'
                ]);
            }
        }

        // check groups that are to be removed
        $groupsRemoved = array_diff($groupsBefore, $groupsNow);

        if (!empty($groupsRemoved)) {
            $groupsHandled = [];

            foreach ($groupsRemoved as $groupId) {
                $CryptoGroup             = CryptoActors::getCryptoGroup($groupId);
                $groupsHandled[$groupId] = true;

                try {
                    $CryptoGroup->removeCryptoUser($CryptoUser);
                } catch (\Exception $Exception) {
                    QUI::getMessagesHandler()->addAttention(
                        QUI::getLocale()->get(
                            'sequry/core',
                            'attention.events.onusersavebegin.remove.user.error',
                            [
                                'userId'    => $User->getId(),
                                'userName'  => $User->getName(),
                                'groupId'   => $CryptoGroup->getId(),
                                'groupName' => $CryptoGroup->getAttribute('name'),
                                'error'     => $Exception->getMessage()
                            ]
                        )
                    );

                    $groupsNow[] = $CryptoGroup->getId();
                }
            }
        }

        if ($addedAdminUsers) {
            QUI::getMessagesHandler()->addSuccess(
                QUI::getLocale()->get(
                    'sequry/core',
                    'message.ajax.actors.groups.addAdminUser.success'
                )
            );
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
     * @param QUI\Users\User $User
     * @return void
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
            throw new QUI\Exception([
                'sequry/core',
                'exception.events.user.delete.no.permission'
            ]);
        }

        if (QUI::isSystem()) {
            CryptoActors::getCryptoUser($User->getId())->delete();
            return;
        }

        QUI::getAjax()->triggerGlobalJavaScriptCallback(
            'userDeleteConfirm',
            [
                'userId'   => $User->getId(),
                'userName' => $User->getUsername()
            ]
        );

        throw new QUI\Exception([
            'sequry/core',
            'exception.events.user.delete.info'
        ]);
    }

    /**
     * event: on group delete
     *
     * Throws an exception so the standard group deletion progress is immediately aborted
     * and an own procedure can be called from the frontend
     *
     * @param QUI\Groups\Group $Group
     * @return void
     * @throws QUI\Exception
     */
    public static function onGroupDelete($Group)
    {
        if (!self::$triggerGroupDeleteConfirm) {
            return;
        }

        $SessionUser = QUI::getUserBySession();

        if (!$SessionUser->isSU()) {
            throw new QUI\Exception([
                'sequry/core',
                'exception.events.group.delete.no.permission'
            ]);
        }

        QUI::getAjax()->triggerGlobalJavaScriptCallback(
            'groupDeleteConfirm',
            [
                'groupId'   => $Group->getId(),
                'groupName' => $Group->getAttribute('name')
            ]
        );

        throw new QUI\Exception([
            'sequry/core',
            'exception.events.group.delete.info'
        ]);
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
        $cssFile = URL_OPT_DIR.'sequry/core/bin/style.css';
        echo '<link href="'.$cssFile.'" rel="stylesheet" type="text/css"/>';
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
        $jsFile = URL_OPT_DIR.'sequry/core/bin/onAdminLoadFooter.js';
        echo '<script src="'.$jsFile.'"></script>';
    }

    /**
     * quiqqer/quiqqer: onGroupCreate
     *
     * @param QUI\Groups\Group $Group
     * @return void
     */
    public static function onGroupCreate(QUI\Groups\Group $Group)
    {
        try {
            $Manager = QUI::getPermissionManager();
            $Manager->setPermissions(
                $Group,
                [
                    'gpm.cryptodata.create'                          => true,
                    'gpm.cryptodata.share'                           => true,
                    'gpm.cryptodata.share_group'                     => true,
                    'quiqqer.frontendUsers.profile.view.user.data'   => true,
                    'quiqqer.frontendUsers.profile.view.user.avatar' => true
                ],
                QUI::getUsers()->getSystemUser()
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * sequry/core: onPasswordDelete
     *
     * @param Password $Password
     * @return void
     */
    public static function onPasswordDelete(Password $Password)
    {
        // delete password links
        foreach (PasswordLinks::getLinksByPasswordId($Password->getId()) as $PasswordLink) {
            $PasswordLink->delete();
        }
    }

    /**
     * sequry/core: onPasswordOwnerChange
     *
     * @param Password $Password
     * @param CryptoUser|CryptoGroup $NewOwner
     * @return void
     */
    public static function onPasswordOwnerChange(Password $Password, $NewOwner)
    {
        // deactivate all PasswordLinks if new owner is not allowed to use them
        if (!PasswordLinks::isActorAllowedToUsePasswordLinks($Password, $NewOwner)) {
            foreach (PasswordLinks::getLinksByPasswordId($Password->getId()) as $PasswordLink) {
                $PasswordLink->deactivate(false);
            }
        }
    }

    /**
     * sequry/core: onPasswordSecurityClassChange
     *
     * @param Password $Password
     * @param SecurityClass $SecurityClass
     * @return void
     */
    public static function onPasswordSecurityClassChange(Password $Password, SecurityClass $SecurityClass)
    {
        // deactivate all PasswordLinks if new SecurityClass prohibits PasswordLinks
        if (!$SecurityClass->isPasswordLinksAllowed()) {
            foreach (PasswordLinks::getLinksByPasswordId($Password->getId()) as $PasswordLink) {
                $PasswordLink->deactivate(false);
            }
        }
    }
}
