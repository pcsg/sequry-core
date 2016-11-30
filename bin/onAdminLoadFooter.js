require([
    'Ajax',
    'qui/controls/windows/Confirm',
    'Locale'
], function (QUIAjax, QUIConfirm, QUILocale) {
    var lg  = 'pcsg/grouppasswordmanager';
    var pkg = 'pcsg/grouppasswordmanager';

    QUIAjax.registerGlobalJavaScriptCallback(
        'addUsersByGroup',
        function (response, AuthInfo) {

            console.log(arguments);

            require([
                'package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow',
                'package/pcsg/grouppasswordmanager/bin/classes/Actors'
            ], function(MultiAuthWindow, ActorHandler) {
                var Actors = new ActorHandler();

                new MultiAuthWindow({
                    securityClassIds: AuthInfo.securityClassIds,
                    events: {
                        onSubmit: function(AuthData, Popup) {
                            Actors.addUsersToGroup(
                                AuthInfo.groupId,
                                AuthInfo.userIds,
                                AuthData
                            ).then(function(result) {
                                console.log("erfolg");
                            });

                            Popup.close();
                        }
                    }
                }).open();
            });
        }
    );

    QUIAjax.registerGlobalJavaScriptCallback(
        'addGroupsToUser',
        function (response, AuthInfo) {

            console.log(AuthInfo);

            require([
                'package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow',
                'package/pcsg/grouppasswordmanager/bin/classes/Actors'
            ], function(MultiAuthWindow, ActorHandler) {
                var Actors = new ActorHandler();

                new MultiAuthWindow({
                    securityClassIds: AuthInfo.securityClassIds,
                    events: {
                        onSubmit: function(AuthData, Popup) {
                            Actors.addGroupsToUser(
                                AuthInfo.userId,
                                AuthInfo.groupIds,
                                AuthData
                            ).then(function(result) {
                                console.log("erfolg");
                            });

                            Popup.close();
                        }
                    }
                }).open();
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
});