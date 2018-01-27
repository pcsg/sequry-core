require.config({
    paths: {
        "ClipboardJS"  : URL_OPT_DIR + 'bin/clipboard/dist/clipboard',
        "html5tooltips": URL_OPT_DIR + 'quiqqer/tooltips/bin/html5tooltips'
    }
});

require([
    'qui/QUI',
    'Ajax',
    'qui/controls/windows/Confirm',
    'package/pcsg/grouppasswordmanager/bin/Passwords',
    'package/pcsg/grouppasswordmanager/bin/Actors',
    'Locale'
], function (QUI, QUIAjax, QUIConfirm, Passwords, Actors, QUILocale) {
    "use strict";

    var lg  = 'pcsg/grouppasswordmanager';
    var pkg = 'pcsg/grouppasswordmanager';

    var loadExecute = 0;

    var loadPasswordCategoryPanel = function () {
        loadExecute++;

        if (loadExecute == 10) {
            return;
        }

        var ColumnElm = document.getElement('.qui-column'),
            Column    = QUI.Controls.getById(ColumnElm.get('data-quiid'));

        var panels = Column.getChildren(),
            length = Object.getLength(panels);

        if (length === 0) {
            loadPasswordCategoryPanel();
            return;
        }

        for (var i in panels) {
            if (!panels.hasOwnProperty(i)) {
                continue;
            }

            if (panels[i].getType() === 'package/pcsg/grouppasswordmanager/bin/controls/categories/Panel') {
                return;
            }
        }

        require([
            'package/pcsg/grouppasswordmanager/bin/controls/categories/Panel'
        ], function (CategoryPanel) {
            Column.appendChild(new CategoryPanel(), 0);
        });
    };

    QUI.addEvents({
        onQuiqqerLoaded: function () {
            var panels = QUI.Controls.getByType(
                'package/pcsg/grouppasswordmanager/bin/controls/passwords/Panel'
            );

            if (!panels.length) {
                Passwords.openPasswordListPanel();
                loadPasswordCategoryPanel();

                return;
            }

            var PasswordPanel = panels[0];

            PasswordPanel.addEvents({
                onDestroy: function () {
                    window.PasswordList = null;
                }
            });

            window.PasswordList = PasswordPanel;
            loadPasswordCategoryPanel();
        }
    });

    QUIAjax.registerGlobalJavaScriptCallback(
        'addUsersByGroup',
        function (response, AuthInfo) {
            require([
                'package/pcsg/grouppasswordmanager/bin/Actors'
            ], function (Actors) {
                Actors.addUsersToGroup(
                    AuthInfo.groupId,
                    AuthInfo.userIds
                ).then(function () {
                    // nothing
                }, function () {
                    // nothing
                });
            });
        }
    );

    QUIAjax.registerGlobalJavaScriptCallback(
        'addGroupsToUser',
        function (response, AuthInfo) {
            require([
                'package/pcsg/grouppasswordmanager/bin/Actors'
            ], function (Actors) {
                Actors.addGroupsToUser(
                    AuthInfo.userId,
                    AuthInfo.groupIds
                ).then(function () {
                    // nothing
                }, function () {
                    // nothing
                });
            });
        }
    );

    QUIAjax.registerGlobalJavaScriptCallback(
        'userDeleteConfirm',
        function (response, User) {
            var Confirm = new QUIConfirm({
                icon       : 'fa fa-exclamation-triangle',
                texticon   : 'fa fa-exclamation-triangle',
                title      : QUILocale.get(lg, 'event.user.delete.confirm.title'),
                information: QUILocale.get(lg, 'event.user.delete.confirm.information', {
                    userId  : User.userId,
                    userName: User.userName
                }),
                events     : {
                    onSubmit: function () {
                        Confirm.Loader.show();

                        QUIAjax.post('package_pcsg_grouppasswordmanager_ajax_actors_delete', function () {
                            Confirm.close();
                        }, {
                            'package': pkg,
                            onError  : function () {
                                Confirm.Loader.hide();
                            },
                            id       : User.userId,
                            type     : 'user'
                        });
                    }
                }
            });

            Confirm.open();
        }
    );

    QUIAjax.registerGlobalJavaScriptCallback(
        'groupDeleteConfirm',
        function (response, Group) {
            var Confirm = new QUIConfirm({
                icon       : 'fa fa-exclamation-triangle',
                texticon   : 'fa fa-exclamation-triangle',
                title      : QUILocale.get(lg, 'event.group.delete.confirm.title'),
                information: QUILocale.get(lg, 'event.group.delete.confirm.information', {
                    groupId  : Group.groupId,
                    groupName: Group.groupName
                }),
                events     : {
                    onSubmit: function () {
                        Confirm.Loader.show();

                        QUIAjax.post('package_pcsg_grouppasswordmanager_ajax_actors_delete', function () {
                            Confirm.close();
                        }, {
                            'package': pkg,
                            onError  : function () {
                                Confirm.Loader.hide();
                            },
                            id       : Group.groupId,
                            type     : 'group'
                        });
                    }
                }
            });

            Confirm.open();
        }
    );

    QUIAjax.registerGlobalJavaScriptCallback(
        'showRecoveryCode',
        function (response, Data) {
            require([
                'package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/CodePopup'
            ], function (RecoveryCodePopup) {
                new RecoveryCodePopup({
                    RecoveryCodeData: Data.recoveryCode
                }).open();
            });
        }
    );

    window.addEvent('quiqqerLoaded', function() {
        Actors.getGroupAdminStatus().then(function(Status) {
            if (!Status.isGroupAdmin) {
                return;
            }

            require([
                'package/pcsg/grouppasswordmanager/bin/controls/actors/groupadmins/GroupAdminButton'
            ], function(GroupAdminButton) {
                new GroupAdminButton({
                    openRequests: Status.openRequests
                }).inject(
                    document.getElement('.qui-menu-container')
                );
            });
        });
    });
});