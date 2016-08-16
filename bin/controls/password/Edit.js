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
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
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
    'package/pcsg/grouppasswordmanager/bin/controls/password/Authenticate',
    'package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select',
    'package/pcsg/grouppasswordmanager/bin/controls/actors/Select',
    'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Content',


    'text!package/pcsg/grouppasswordmanager/bin/controls/password/Edit.html'
    //'css!package/pcsg/grouppasswordmanager/bin/controls/password/Edit.css'

], function (QUI, QUIControl, QUILocale, Mustache, PasswordHandler,
             AuthenticationControl, SecurityClassSelect, ActorSelect, PasswordContent, template) {
    "use strict";

    var lg        = 'pcsg/grouppasswordmanager',
        Passwords = new PasswordHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/Edit',

        Binds: [
            '$onInject',
            '$onDestroy',
            '$save',
            '$onPasswordDataLoaded'
        ],

        options: {
            passwordId: false  // password ID
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject : this.$onInject,
                onDestroy: this.$onDestroy
            });

            this.$PasswordData    = null;
            this.$owner           = false;
            this.$PassContent     = null;
            this.$AuthData        = null;
            this.$securityClassId = false;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            var lg_prefix = 'create.template.';

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
            var pwId = this.getAttribute('passwordId');

            var AuthControl = new AuthenticationControl({
                passwordId: pwId,
                events         : {
                    onSubmit: function (AuthData) {
                        self.$AuthData = AuthData;

                        Passwords.get(
                            pwId,
                            AuthData
                        ).then(
                            function (PasswordData) {
                                AuthControl.destroy();

                                self.$PasswordData    = PasswordData;
                                self.$AuthData        = AuthData;
                                self.$securityClassId = PasswordData.securityClassId;

                                self.$onPasswordDataLoaded();
                            },
                            function () {
                                // @todo
                            }
                        );
                    },
                    onAbort: function() {
                        self.fireEvent('close');
                    },
                    onClose : function () {
                        self.fireEvent('close');
                    }
                }
            });

            AuthControl.open();
        },

        $onPasswordDataLoaded: function () {
            var self = this;

            var SecurityClassElm = this.$Elm.getElement(
                'span.pcsg-gpm-security-classes'
            );

            var OwnerSelectElm = this.$Elm.getElement(
                'span.pcsg-gpm-password-owner'
            );

            this.$SecurityClassSelect = new SecurityClassSelect({
                initialValue: this.$securityClassId,
                events      : {
                    onLoaded: function () {
                        self.$insertData();
                        self.fireEvent('loaded');
                    },
                    onChange: function (value) {
                        OwnerSelectElm.set('html', '');

                        self.$OwnerSelect = new ActorSelect({
                            max            : 1,
                            securityClassId: value,
                            events         : {
                                onChange: function () {
                                    self.$owner = self.$OwnerSelect.getValue();
                                }
                            }
                        }).inject(OwnerSelectElm);

                        self.$securityClassId = value;
                    }
                }
            }).inject(
                SecurityClassElm
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
            this.$OwnerSelect.clear();

            if (this.$PasswordData.ownerId && this.$PasswordData.ownerType) {
                switch (parseInt(this.$PasswordData.ownerType)) {
                    case 1:
                        this.$OwnerSelect.addItem('u' + this.$PasswordData.ownerId);
                        break;

                    case 2:
                        this.$OwnerSelect.addItem('g' + this.$PasswordData.ownerId);
                        break;
                }
            }

            // set form values
            this.$Elm.getElement('.pcsg-gpm-password-title').value       = self.$PasswordData.title;
            this.$Elm.getElement('.pcsg-gpm-password-description').value = self.$PasswordData.description;

            var PayloadElm = this.$Elm.getElement('.pcsg-gpm-password-payload');

            PayloadElm.set('html', '');

            this.$PassContent = new PasswordContent({
                type    : this.$PasswordData.dataType,
                editable: true,
                events  : {
                    onLoaded: function () {
                        self.$PassContent.setData(self.$PasswordData.payload);
                    }
                }
            }).inject(PayloadElm);
        },

        /**
         * event: on destroy
         */
        $onDestroy: function () {
            this.$PasswordData = null;
            this.$AuthData     = null;
        },

        /**
         * Edit the password
         *
         * @returns {Promise}
         */
        submit: function () {
            var self = this;

            var PasswordData = {
                securityClassId: this.$SecurityClassSelect.getValue(),
                title          : this.$Elm.getElement('.pcsg-gpm-password-title').value,
                description    : this.$Elm.getElement('.pcsg-gpm-password-description').value,
                payload        : this.$PassContent.getData(),
                dataType       : this.$PassContent.getPasswordType()
            };

            var actors = this.$OwnerSelect.getActors();

            if (!actors.length) {
                QUI.getMessageHandler(function (MH) {
                    MH.addAttention(
                        QUILocale.get(lg, 'password.create.submit.no.owner.assigned')
                    )
                });

                return;
            }

            PasswordData.owner = actors[0];

            Passwords.editPassword(
                this.getAttribute('passwordId'),
                PasswordData,
                this.$AuthData
            ).then(
                function (PasswordData) {
                    self.$PasswordData = PasswordData;
                    self.$insertData();
                },
                function () {
                    // @todo error handling
                }
            );
        }
    });
});
