/**
 * Control for creating a new password
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/Register
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/auth/Register.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/auth/Register.css
 *
 * @event onFinish
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/Register', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/utils/Form',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/Authentication',

    'Ajax',

    'text!package/pcsg/grouppasswordmanager/bin/controls/auth/Register.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/Register.css'

], function (QUI, QUIControl, QUIFormUtils, QUILocale, Mustache, Authentication,
             Ajax, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/auth/Register',

        Binds: [
            '$onInject',
            'submit'
        ],

        options: {
            'authPluginId': false // id of auth plugin the registration is for
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

                    self.fireEvent('finish');
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
                this.$AuthPluginControl.getRegistrationData()
            );
        }
    });
});
