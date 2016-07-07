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

    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/actors/Select',
    'package/pcsg/grouppasswordmanager/bin/controls/actors/ActorBox',

    'css!package/pcsg/grouppasswordmanager/bin/controls/password/Share.css'

], function (QUI, QUIControl, QUIButton, QUILocale, PasswordHandler,
             ActorSelect, ActorBox) {
    "use strict";

    var lg        = 'pcsg/grouppasswordmanager',
        Passwords = new PasswordHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/Share',

        Binds: [
            '$onInject',
            '$save',
            '$insertData'
        ],

        options: {
            passwordId : false, // passwordId
            AuthData   : false, // authentication data
            ParentPanel: false  // parent panel that builds this control
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.$ShareData = null;

            //this.getAttribute('ParentPanel').addEvents({
            //    onSave: this.$save
            //});
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            var self      = this,
                lg_prefix = 'create.template.';

            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'gpm-password-share',
                html   : '<div class="gpm-password-share-select"></div>' +
                '<div class="gpm-password-share-actors">' +
                '<div class="gpm-password-share-actors-users"></div>' +
                '<div class="gpm-password-share-actors-groups"></div>' +
                '</div>'
            });

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
                    this.$ActorSelect.addItem('u' + id, type);
                    break;

                case 'group':
                    this.$ActorSelect.addItem('g' + id, type);
                    break;
            }
        },

        $insertData: function () {
            for (var type in this.$ShareData) {
                if (!this.$ShareData.hasOwnProperty(type)) {
                    continue;
                }

                var actors = this.$ShareData[type];

                for (var i = 0, len = actors.length; i < len; i++) {
                    switch (type) {
                        case 'users':
                            this.$addActor(this.$ShareData[type], 'user');
                            break;

                        case 'groups':
                            this.$addActor(this.$ShareData[type], 'group');
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

            var ActorSelectElm = this.$Elm.getElement(
                '.gpm-password-share-select'
            );

            Passwords.getSecurityClassId(
                this.getAttribute('passwordId')
            ).then(function (securityClassId) {

            });

            var pwId = this.getAttribute('passwordId');

            Promise.all([
                Passwords.getSecurityClassId(pwId),
                Passwords.getShareData(pwId, this.getAttribute('AuthData'))
            ]).then(function (result) {
                var securityClassId = result[0];

                self.$ShareData   = result[1];
                self.$ActorSelect = new ActorSelect({
                    securityClassId: securityClassId
                }).inject(ActorSelectElm);

                self.$insertData();
                self.fireEvent('loaded');
            });
        },

        /**
         * Share the password
         *
         * @returns {Promise}
         */
        save: function () {
            var self = this;

            var shareData = [];
            var actors    = this.$ActorSelect.getValue();

            if (actors !== '') {
                actors = actors.split(',');

                for (var i = 0, len = actors.length; i < len; i++) {
                    var id = actors[i];

                    if (id.charAt(0) === 'u') {
                        shareData.push({
                            id  : id.substr(1),
                            type: 'user'
                        });

                        continue;
                    }

                    shareData.push({
                        id  : id.substr(1),
                        type: 'group'
                    });
                }
            }

            Passwords.setShareData(
                this.getAttribute('passwordId'),
                shareData,
                this.getAttribute('AuthData')
            ).then(
                function (ShareData) {
                    self.$ShareData = ShareData;
                    self.$insertData();
                },
                function () {
                    console.log("error");
                }
            );
        }
    });
});
