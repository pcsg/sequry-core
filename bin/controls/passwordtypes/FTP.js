/**
 * FTP Input type for passwords
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/FTP
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
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/FTP.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/FTP.css
 *
 * @event onLoaded
 * @event onFinish
 */
define('package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/FTP', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate',
    'package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select',
    'package/pcsg/grouppasswordmanager/bin/controls/actors/EligibleActorSelect',


    'text!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/FTP.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/FTP.css'

], function (QUI, QUIControl, QUILocale, Mustache, PasswordHandler,
             AuthenticationControl, SecurityClassSelect, ActorSelect, template) {
    "use strict";

    var lg        = 'pcsg/grouppasswordmanager',
        Passwords = new PasswordHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/FTP',

        Binds: [
            '$onInject'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.$PasswordData = null;
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
                'class': 'pcsg-gpm-password-create',
                html   : Mustache.render(template, {
                    title              : QUILocale.get(lg, lg_prefix + 'title'),
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

            // insert security class select
            var SecurityClassElm = this.$Elm.getElement(
                'span.pcsg-gpm-security-classes'
            );

            var OwnerSelectElm = this.$Elm.getElement(
                'span.pcsg-gpm-password-owner'
            );

            this.$SecurityClassSelect = new SecurityClassSelect({
                events: {
                    onLoaded: function (Select) {
                        self.$OwnerSelect = new ActorSelect({
                            securityClassId: Select.getValue(),
                            events         : {
                                onLoaded: function () {
                                    self.fireEvent('loaded');
                                }
                            }
                        }).inject(OwnerSelectElm);
                    }
                }
            }).inject(
                SecurityClassElm
            );

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            // @todo
        },

        /**
         * Create the field
         *
         * @returns {Promise}
         */
        submit: function () {
            var self = this;

            this.$PasswordData = {
                securityClassId: this.$SecurityClassSelect.getValue(),
                title          : this.$Elm.getElement('input.pcsg-gpm-password-title').value,
                description    : this.$Elm.getElement('input.pcsg-gpm-password-description').value,
                payload        : this.$Elm.getElement('input.pcsg-gpm-password-payload').value,
                owner          : this.$OwnerSelect.getActor()
            };

            var AuthControl = new AuthenticationControl({
                securityClassId: this.$SecurityClassSelect.getValue(),
                events         : {
                    onSubmit: function (AuthData) {
                        Passwords.createPassword(
                            self.$PasswordData,
                            AuthData
                        ).then(
                            function () {
                                self.$PasswordData = null;
                                AuthControl.destroy();
                                self.fireEvent('finish');
                            },
                            function () {
                                // @todo
                            }
                        );
                    }
                }
            });

            AuthControl.open();
        }
    });
});
