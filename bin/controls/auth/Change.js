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
    'qui/utils/Form',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',

    'Ajax',

    'text!package/pcsg/grouppasswordmanager/bin/controls/auth/Change.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/Change.css'

], function (QUI, QUIControl, QUIFormUtils, QUILocale, Mustache, AuthHandler,
             Ajax, template) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager',
        Authentication = new AuthHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/auth/Change',

        Binds: [
            '$onInject',
            'submit'
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

            this.$Parent = this.getAttribute('Parent');

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
                '.pcsg-gpm-auth-change-control'
            );

            Authentication.getChangeAuthenticationControl(
                this.getAttribute('authPluginId')
            ).then(function (authPluginControlPath) {
                require([
                    authPluginControlPath
                ], function (Control) {
                    self.$AuthPluginControl = new Control({
                        events : {
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
         * event : on inject
         */
        $onInject: function () {
            // @todo
        },

        /**
         * Change current user with plugin
         *
         * @returns {Promise}
         */
        submit: function () {
            if (!this.$AuthPluginControl.check()) {
                return;
            }

            return Authentication.changeAuthInformation(
                this.getAttribute('authPluginId'),
                this.$AuthPluginControl.getOldAuthData(),
                this.$AuthPluginControl.getNewAuthData()
            );
        }
    });
});
