/**
 * Control for changing authentication information
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/Change
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/auth/Change.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/auth/Change.css
 *
 * @event onFinish
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/Change', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/utils/Form',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',

    'Ajax',

    'text!package/pcsg/grouppasswordmanager/bin/controls/auth/Change.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/Change.css'

], function (QUI, QUIControl, QUIButton, QUIFormUtils, QUILocale, Mustache,
             AuthHandler, Ajax, template) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager',
        Authentication = new AuthHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/auth/Change',

        Binds: [
            '$onInject',
            'submit',
            '$showRecovery'
        ],

        options: {
            Parent        : false,  // Parent control
            'authPluginId': false   // id of auth plugin the registration is for
        },

        initialize: function (options) {
            var self = this;

            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.$Parent       = this.getAttribute('Parent');
            this.$recoveryMode = false;

            if (this.$Parent) {
                this.$Parent.addEvents({
                    onSubmit: self.submit
                });
            }
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            var self      = this,
                lg_prefix = 'auth.change.template.';

            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-auth-register',
                html   : Mustache.render(template, {
                    title    : QUILocale.get(lg, lg_prefix + 'title'),
                    basicData: QUILocale.get(lg, lg_prefix + 'basicData'),
                    recovery : QUILocale.get(lg, lg_prefix + 'recovery')
                })
            });

            new QUIButton({
                textimage: 'fa fa-question-circle',
                text     : QUILocale.get(lg, 'auth.change.btn.recovery'),
                events   : {
                    onClick: this.$showRecovery
                }
            }).inject(
                this.$Elm.getElement(
                    '.pcsg-gpm-auth-change-recovery'
                )
            );

            var AuthPluginControlElm = this.$Elm.getElement(
                '.pcsg-gpm-auth-change-control'
            );

            Authentication.getChangeAuthenticationControl(
                this.getAttribute('authPluginId')
            ).then(function (authPluginControlPath) {
                require([
                    authPluginControlPath
                ], function (Control) {
                    self.$AuthPluginControl = new Control({
                        events: {
                            onSubmit: self.submit
                        }
                    }).inject(
                        AuthPluginControlElm
                    );

                    self.fireEvent('finish');
                });
            });

            return this.$Elm;
        },

        /**
         * Show recovery information and input
         */
        $showRecovery: function () {
            var self = this;

            Authentication.getRecoveryCodeId(
                this.getAttribute('authPluginId')
            ).then(function(recoveryCodeId) {
                if (!recoveryCodeId) {
                    return;
                }

                self.$recoveryMode = true;

                var RecoveryElm = self.$Elm.getElement(
                    '.pcsg-gpm-auth-change-recovery'
                );

                RecoveryElm.set(
                    'html',
                    '<span class="pcsg-gpm-auth-change-recovery-info">' + QUILocale.get(lg, 'auth.recovery.information') + '</span>' +
                    '<span class="pcsg-gpm-auth-change-recovery-code">' +
                        QUILocale.get(lg, 'auth.recovery.code', {
                            recoveryCodeId: recoveryCodeId
                        }) +
                    '</span>' +
                    '<div class="pcsg-gpm-auth-change-recovery-inputs"></div>'
                );

                var InputsElm = RecoveryElm.getElement('.pcsg-gpm-auth-change-recovery-inputs');

                for (var i = 0; i < 5; i++) {
                    var InputElm = new Element('input', {
                        'class'  : 'pcsg-gpm-auth-change-recovery-input',
                        'data-id': i + 1,
                        type     : 'text',
                        maxlength: 5,
                        events   : {
                            input: function (event) {
                                var Elm = event.target;

                                if (Elm.value.length < 5) {
                                    return;
                                }

                                var id = parseInt(Elm.getProperty('data-id'));

                                if (id === 5) {
                                    return;
                                }

                                RecoveryElm.getElement('input[data-id="' + (id + 1) + '"]').focus();
                            }
                        }
                    }).inject(InputsElm);

                    if (i === 0) {
                        InputElm.focus();
                    }
                }
            });
        },

        /**
         * Get recovery code
         *
         * @return {string} - the recovery code
         */
        $getRecoveryCode: function () {
            if (!this.$recoveryMode) {
                return;
            }

            var inputs = this.$Elm.getElement('.pcsg-gpm-auth-change-recovery-inputs').getElements('input');
            var code   = '';

            for (var i = 0, len = inputs.length; i < len; i++) {
                code += inputs[i].value;
            }

            return code;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            // @todo
        },

        /**
         * Check validity of changes
         *
         * @return {boolean}
         */
        check: function () {
            if (!this.$recoveryMode) {
                return this.$AuthPluginControl.check();
            }

            return true;
        },

        /**
         * Change current user with plugin
         *
         * @returns {Promise}
         */
        submit: function () {
            var OldAuthData = this.$AuthPluginControl.getOldAuthData();

            if (this.$recoveryMode) {
                OldAuthData = this.$getRecoveryCode();
            }

            return Authentication.changeAuthInformation(
                this.getAttribute('authPluginId'),
                OldAuthData,
                this.$AuthPluginControl.getNewAuthData(),
                this.$recoveryMode
            );
        }
    });
});
