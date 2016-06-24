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
 * @require package/pcsg/grouppasswordmanager/bin/controls/auth/SecurityClassSelect
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
    'package/pcsg/grouppasswordmanager/bin/controls/actors/EligibleActorSelect',
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
            '$save'
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
         * @param {Object} ActorData
         */
        $addActor: function(ActorData)
        {
            switch (ActorData.type) {
                case 'user':
                    ActorData.type = 1;
                    this.$ActorBoxUsers.addActor(ActorData);
                    break;

                case 'group':
                    ActorData.type = 2;
                    this.$ActorBoxGroups.addActor(ActorData);
                    break;
            }
        },

        $insertData: function() {
            // @todo
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            var self = this;

            var ActorSelectElm = this.$Elm.getElement(
                '.gpm-password-share-select'
            );

            // build actor boxes
            this.$ActorBoxUsers = new ActorBox().inject(
                this.$Elm.getElement(
                    '.gpm-password-share-actors-users'
                )
            );

            this.$ActorBoxGroups = new ActorBox().inject(
                this.$Elm.getElement(
                    '.gpm-password-share-actors-groups'
                )
            );

            this.$AddActorBtn = new QUIButton({
                icon: 'fa fa-plus',
                events: {
                    onClick: function() {

                        var ActorData = self.$ActorSelect.getActor();

                        ActorData.name = self.$ActorSelect.getActorLabel();

                        self.$addActor(ActorData);
                    }
                }
            }).inject(ActorSelectElm);

            // get password data
            var pwId = this.getAttribute('passwordId');

            Promise.all([
                Passwords.getSecurityClassId(pwId),
                Passwords.getShareData(pwId, this.getAttribute('AuthData'))
            ]).then(function (result) {
                var securityClassId = result[0];

                self.$ShareData   = result[1];
                self.$ActorSelect = new ActorSelect({
                    securityClassId: securityClassId,
                    events         : {
                        onLoaded: function () {
                            self.$insertData();
                            self.fireEvent('loaded');
                        }
                    }
                }).inject(ActorSelectElm);
            });
        },

        /**
         * Share the password
         *
         * @returns {Promise}
         */
        save: function () {
            var actors = this.$ActorBoxUsers.getActors().concat(
                this.$ActorBoxGroups.getActors()
            );

            Passwords.setShareData(
                this.getAttribute('passwordId'),
                actors,
                this.getAttribute('AuthData')
            ).then(
                function() {
                    console.log("success");
                },
                function() {
                    console.log("error");
                }
            );
        }
    });
});
