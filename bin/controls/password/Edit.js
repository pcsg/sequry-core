/**
 * Control for editing a password
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/Edit
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
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/password/Edit.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/password/Edit.css
 *
 * @event onLoaded
 * @event onAuthAbort - on user authentication abort
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/Edit', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/SecurityClassSelect',
    'package/pcsg/grouppasswordmanager/bin/controls/actors/EligibleActorSelect',


    'text!package/pcsg/grouppasswordmanager/bin/controls/password/Edit.html'
    //'css!package/pcsg/grouppasswordmanager/bin/controls/password/Edit.css'

], function (QUI, QUIControl, QUILocale, Mustache, PasswordHandler,
             AuthenticationControl, SecurityClassSelect, ActorSelect, template) {
    "use strict";

    var lg        = 'pcsg/grouppasswordmanager',
        Passwords = new PasswordHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/Edit',

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

            this.$PasswordData = null;

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
                'class': 'pcsg-gpm-password-edit',
                html   : Mustache.render(template, {
                    title              : QUILocale.get(lg, 'edit.template.title'),
                    basicData          : QUILocale.get(lg, lg_prefix + 'basicData'),
                    securityClass      : QUILocale.get(lg, lg_prefix + 'securityClass'),
                    passwordTitle      : QUILocale.get(lg, lg_prefix + 'passwordTitle'),
                    passwordDescription: QUILocale.get(lg, lg_prefix + 'passwordDescription'),
                    payload            : QUILocale.get(lg, lg_prefix + 'payload'),
                    passwordPayload    : QUILocale.get(lg, lg_prefix + 'passwordPayload'),
                    payloadWarning     : QUILocale.get(lg, lg_prefix + 'payloadWarning'),
                    owner              : QUILocale.get(lg, lg_prefix + 'owner'),
                    passwordOwner      : QUILocale.get(lg, lg_prefix + 'passwordOwner')
                })
            });

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            var self = this;

            Passwords.get(
                self.getAttribute('passwordId'),
                self.getAttribute('AuthData')
            ).then(
                function (PasswordData) {
                    self.$PasswordData = PasswordData;

                    // insert security class select
                    var SecurityClassElm = self.$Elm.getElement(
                        'span.pcsg-gpm-security-classes'
                    );

                    var OwnerSelectElm = self.$Elm.getElement(
                        'span.pcsg-gpm-password-owner'
                    );

                    self.$SecurityClassSelect = new SecurityClassSelect({
                        events: {
                            onLoaded: function (Select) {
                                self.$OwnerSelect = new ActorSelect({
                                    securityClassId: Select.getValue(),
                                    events         : {
                                        onLoaded: function () {
                                            self.$insertData();
                                            self.fireEvent('loaded');
                                        }
                                    }
                                }).inject(OwnerSelectElm);
                            }
                        }
                    }).inject(
                        SecurityClassElm
                    );
                },
                function () {
                    self.destroy();
                }
            );
        },

        /**
         * Insert Data to edit form
         */
        $insertData: function () {
            var self = this;

            if (!this.$PasswordData) {
                this.destroy();
            }

            // set security class
            this.$SecurityClassSelect.setValue(this.$PasswordData.securityClassId);

            // set owner
            var ownerType = 'user';

            if (self.$PasswordData.ownerType == 2) {
                ownerType = 'group';
            }

            this.$OwnerSelect.setActorValue(self.$PasswordData.ownerId, ownerType);

            // set form values
            this.$Elm.getElement('.pcsg-gpm-password-title').value       = self.$PasswordData.title;
            this.$Elm.getElement('.pcsg-gpm-password-description').value = self.$PasswordData.description;
            this.$Elm.getElement('.pcsg-gpm-password-payload').value     = self.$PasswordData.payload;
        },

        /**
         * Return data from form
         *
         * @returns {Object}
         */
        $getData: function () {
            var PasswordData = {
                securityClassId: this.$SecurityClassSelect.getValue(),
                title          : this.$Elm.getElement('.pcsg-gpm-password-title').value,
                description    : this.$Elm.getElement('.pcsg-gpm-password-description').value,
                payload        : this.$Elm.getElement('.pcsg-gpm-password-payload').value,
                owner          : this.$OwnerSelect.getActor()
            };

            return PasswordData;
        },

        /**
         * Edit the password
         *
         * @returns {Promise}
         */
        save: function () {
            var self  = this;
            var Panel = this.getAttribute('ParentPanel');

            Panel.Loader.show();

            Passwords.editPassword(
                this.getAttribute('passwordId'),
                this.$getData(),
                this.getAttribute('AuthData')
            ).then(
                function (PasswordData) {
                    self.$PasswordData = PasswordData;
                    self.$insertData();
                    Panel.Loader.hide();
                },
                function () {
                    Panel.Loader.hide();
                }
            );
        }
    });
});
