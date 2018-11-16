/**
 * Control for creating a new password
 *
 * @module package/sequry/core/bin/controls/auth/Register
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onFinish [self] - Fires if the control has finished loading
 */
define('package/sequry/core/bin/controls/auth/Register', [

    'qui/controls/Control',
    'Locale',
    'Mustache',

    'package/sequry/core/bin/Authentication',

    'text!package/sequry/core/bin/controls/auth/Register.html',
    'css!package/sequry/core/bin/controls/auth/Register.css'

], function (QUIControl, QUILocale, Mustache, Authentication, template) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/sequry/core/bin/controls/auth/Register',

        Binds: [
            '$onInject',
            'submit'
        ],

        options: {
            authPluginId: false // id of auth plugin the registration is for
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            var self      = this,
                lg_prefix = 'auth.template.';

            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-auth-register',
                html   : Mustache.render(template, {
                    title    : QUILocale.get(lg, lg_prefix + 'title'),
                    basicData: QUILocale.get(lg, lg_prefix + 'basicData')
                })
            });

            var AuthPluginControlElm = this.$Elm.getElement(
                '.pcsg-gpm-auth-register-control'
            );

            Authentication.getRegistrationControl(
                this.getAttribute('authPluginId')
            ).then(function (authPluginControlPath) {
                require([
                    authPluginControlPath
                ], function (Control) {
                    self.$AuthPluginControl = new Control().inject(
                        AuthPluginControlElm
                    );

                    self.$AuthPluginControl.focus();

                    self.fireEvent('finish', [self]);
                });
            });

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            // @todo
        },

        /**
         * Register current user with plugin
         *
         * @returns {Promise}
         */
        submit: function () {
            return Authentication.registerUser(
                this.getAttribute('authPluginId'),
                this.$AuthPluginControl.getAuthData()
            );
        }
    });
});
