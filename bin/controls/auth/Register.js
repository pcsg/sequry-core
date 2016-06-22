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
 * @require package/pcsg/grouppasswordmanager/bin/controls/auth/SecurityClassSelect
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

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',

    'Ajax',

    'text!package/pcsg/grouppasswordmanager/bin/controls/auth/Register.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/Register.css'

], function (QUI, QUIControl, QUIFormUtils, QUILocale, Mustache, AuthHandler,
             Ajax, template) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager',
        Authentication = new AuthHandler();

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

            Authentication.getAuthPluginControl(
                this.getAttribute('authPluginId')
            ).then(function(authPluginControlPath) {
                require([
                    authPluginControlPath
                ], function(Control) {
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
            var self = this;

            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_registerUser', resolve, {
                    'package'   : 'pcsg/grouppasswordmanager',
                    onError     : reject,
                    authPluginId: self.getAttribute('authPluginId'),
                    authData    : self.$AuthPluginControl.getAuthData()
                });
            });
        }
    });
});
