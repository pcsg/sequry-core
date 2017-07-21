/**
 * Control for sharing new password
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/Share
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Mustache
 * @require Locale
 * @require package/pcsg/grouppasswordmanager/bin/classes/Passwords
 * @require package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
 * @require package/pcsg/grouppasswordmanager/bin/controls/actors/EligibleActorSelect
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/password/Share.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/password/Share.css
 *
 * @event onLoaded
 * @event onAuthAbort - on user authentication abort
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/Share', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'Locale',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',
    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/actors/Select',

    'css!package/pcsg/grouppasswordmanager/bin/controls/password/Share.css'

], function (QUI, QUIControl, QUIButton, QUILocale, AuthHandler, PasswordHandler, ActorSelect) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager',
        Passwords      = new PasswordHandler(),
        Authentication = new AuthHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/Share',

        Binds: [
            '$onInject',
            '$save',
            '$insertData',
            '$onDestroy'
        ],

        options: {
            passwordId: false // passwordId
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject : this.$onInject,
                onDestroy: this.$onDestroy
            });

            this.$ShareData = null;
            this.$AuthData  = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'gpm-password-share',
                html   : '<h1>' +
                QUILocale.get(lg, 'controls.password.share.title') +
                '</h1>' +
                '<div class="gpm-password-share-info"></div>' +
                '<div class="gpm-password-share-actors-users">' +
                '<h3>' + QUILocale.get(lg, 'controls.password.share.users.header') + '</h3>' +
                '</div>' +
                '<div class="gpm-password-share-actors-groups">' +
                '<h3>' + QUILocale.get(lg, 'controls.password.share.groups.header') + '</h3>' +
                '</div>'
            })
            ;

            return this.$Elm;
        },

        /**
         * Adds an actor to an acoording actor box
         *
         * @param {number} id - user or group id
         * @param {string} type - "user" or "group"
         */
        $addActor: function (id, type) {
            switch (type) {
                case 'user':
                    this.$ActorSelectUsers.addItem('u' + id, type);
                    break;

                case 'group':
                    this.$ActorSelectGroups.addItem('g' + id, type);
                    break;
            }
        },

        /**
         * Insert actors
         */
        $insertData: function () {
            this.$ActorSelectUsers.clear();
            this.$ActorSelectGroups.clear();

            for (var type in this.$ShareData.sharedWith) {
                if (!this.$ShareData.sharedWith.hasOwnProperty(type)) {
                    continue;
                }

                var actors = this.$ShareData.sharedWith[type];

                for (var i = 0, len = actors.length; i < len; i++) {
                    switch (type) {
                        case 'users':
                            this.$addActor(actors[i], 'user');
                            break;

                        case 'groups':
                            this.$addActor(actors[i], 'group');
                            break;
                    }
                }
            }
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            var self = this;

            var ActorUsersElm = this.$Elm.getElement(
                '.gpm-password-share-actors-users'
            );

            var ActorGroupsElm = this.$Elm.getElement(
                '.gpm-password-share-actors-groups'
            );

            var pwId = this.getAttribute('passwordId');

            Authentication.passwordAuth(pwId).then(function (AuthData) {
                Passwords.getShareData(
                    pwId,
                    AuthData
                ).then(
                    function (ShareData) {
                        self.$Elm.getElement(
                            '.gpm-password-share-info'
                        ).set(
                            'html',
                            QUILocale.get(
                                lg,
                                'controls.password.share.info', {
                                    passwordTitle: ShareData.title,
                                    passwordId   : pwId
                                }
                            )
                        );

                        self.$ShareData = ShareData;
                        self.$AuthData  = AuthData;

                        self.$ActorSelectUsers = new ActorSelect({
                            popupInfo      : QUILocale.get(lg,
                                'controls.password.share.actorselect.users.info'
                            ),
                            actorType      : 'users',
                            securityClassId: ShareData.securityClassId,
                            multiple       : true,
                            filterActorIds : ShareData.ownerUserIds
                        }).inject(ActorUsersElm);

                        self.$ActorSelectGroups = new ActorSelect({
                            popupInfo      : QUILocale.get(lg,
                                'controls.password.share.actorselect.groups.info'
                            ),
                            actorType      : 'groups',
                            securityClassId: ShareData.securityClassId,
                            multiple       : true,
                            filterActorIds : ShareData.ownerGroupIds
                        }).inject(ActorGroupsElm);

                        self.$insertData();
                        self.fireEvent('loaded');
                    },
                    function () {
                        self.fireEvent('close');
                    }
                );
            }, function () {
                self.fireEvent('close');
            });
        },

        /**
         * event: on destroy
         */
        $onDestroy: function () {
            this.$ShareData = null;
            this.$AuthData  = null;
        },

        /**
         * Share the password
         *
         * @returns {Promise}
         */
        submit: function () {
            var self = this;

            var shareData = this.$ActorSelectUsers.getActors().append(
                this.$ActorSelectGroups.getActors()
            );

            return new Promise(function (resolve, reject) {
                Passwords.setShareData(
                    self.getAttribute('passwordId'),
                    shareData,
                    self.$AuthData
                ).then(
                    function () {
                        self.fireEvent('close');
                        //self.$ShareData = ShareData;
                        //self.$insertData();
                    },
                    reject
                );
            });
        }
    });
});
