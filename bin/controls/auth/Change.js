/**
 * Control for changing authentication information
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/Change
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onLoaded [this] - fires when control has finished loading
 * @event onFinish [this] - fires if the user successfully committed new auth information
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/Change', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/loader/Loader',

    'qui/utils/Form',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/Authentication',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/CodePopup',

    'Ajax',

    'text!package/pcsg/grouppasswordmanager/bin/controls/auth/Change.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/Change.css'

], function (QUI, QUIControl, QUIButton, QUILoader, QUIFormUtils, QUILocale, Mustache,
             Authentication, RecoveryCodePopup, Ajax, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

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

            this.Loader             = new QUILoader();
            this.$Parent            = this.getAttribute('Parent');
            this.$AuthControl       = null;
            this.$ChangeAuthControl = null;

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
            var self         = this,
                lg_prefix    = 'auth.change.template.',
                authPluginId = self.getAttribute('authPluginId');

            this.$Elm = this.parent();

            this.Loader.inject(this.$Elm);
            this.Loader.show();

            Promise.all([
                Authentication.getAuthPluginInfo(authPluginId),
                Authentication.getAuthenticationControl(authPluginId),
                Authentication.getChangeAuthenticationControl(authPluginId)
            ]).then(function (result) {
                var AuthPlugin = result[0];

                // set template data
                self.$Elm.set({
                    'class': 'pcsg-gpm-auth-register',
                    html   : Mustache.render(template, {
                        title      : QUILocale.get(lg, lg_prefix + 'title', {
                            authPluginTitle: AuthPlugin.title
                        }),
                        authData   : QUILocale.get(lg, lg_prefix + 'authData'),
                        newAuthData: QUILocale.get(lg, lg_prefix + 'newAuthData')
                    })
                });

                var AuthDataElm    = self.$Elm.getElement('.pcsg-gpm-auth-control');
                var NewAuthDataElm = self.$Elm.getElement('.pcsg-gpm-auth-change-control');

                new QUIButton({
                    textimage: 'fa fa-question-circle',
                    text     : QUILocale.get(lg, 'auth.change.btn.recovery'),
                    events   : {
                        onClick: self.$showRecovery
                    }
                }).inject(
                    self.$Elm.getElement('.pcsg-gpm-auth-recovery')
                );

                // load controls
                var controlPaths = [result[1], result[2]];

                require(controlPaths, function (AuthControl, ChangeAuthControl) {
                    self.$AuthControl = new AuthControl({
                        events: {
                            onSubmit: self.submit
                        }
                    }).inject(AuthDataElm, 'top');

                    self.$AuthControl.focus();

                    self.$ChangeAuthControl = new ChangeAuthControl({
                        events: {
                            onSubmit: self.submit
                        }
                    }).inject(NewAuthDataElm);

                    self.Loader.hide();
                    self.fireEvent('loaded', [self]);
                });
            });

            return this.$Elm;
        },

        /**
         * Show recovery information and input
         */
        $showRecovery: function () {
            this.$Parent.recoverAuthData(this.getAttribute('authPluginId'));
            this.fireEvent('finish', [this]);
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            // @todo
        },

        /**
         * Submit new authentication information
         */
        submit: function () {
            var self         = this;
            var authPluginId = this.getAttribute('authPluginId');

            Authentication.changeAuthInformation(
                authPluginId,
                self.$AuthControl.getAuthData(),
                self.$ChangeAuthControl.getAuthData()
            ).then(function (RecoveryCodeData) {
                if (!RecoveryCodeData) {
                    return;
                }

                new RecoveryCodePopup({
                    RecoveryCodeData: RecoveryCodeData,
                    events          : {
                        onClose: function () {
                            self.fireEvent('finish', [self]);
                        }
                    }
                }).open();
            });
        }
    });
});
