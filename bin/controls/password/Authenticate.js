/**
 * Control authentication for a password operation
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/Authenticate
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/loader/Loader
 * @require Locale
 * @require package/pcsg/grouppasswordmanager/bin/classes/Passwords
 * @require package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/password/Authenticate.css
 *
 * @event onFinish
 * @event onAbort - on AuthPopup user close
 * @event onClose - on AuthPopup close
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/Authenticate', [

    'qui/controls/loader/Loader',
    'Locale',

    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate',

    'css!package/pcsg/grouppasswordmanager/bin/controls/password/Authenticate.css'

], function (QUILoader, QUILocale, PasswordHandler, AuthenticationControl) {
    "use strict";

    var lg        = 'pcsg/grouppasswordmanager',
        Passwords = new PasswordHandler();

    return new Class({

        Extends: AuthenticationControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/Authenticate',

        Binds: [
            '$onLoaded',
            '$getPasswordId'
        ],

        options: {
            'passwordId': false // id of the password the authentication is for
        },

        initialize: function (options) {
            this.parent(
                Object.merge(
                    options, {
                        beforeOpen: this.$getPasswordId
                    }
                )
            );

            this.addEvents({
                onLoaded: this.$onLoaded
            });
        },

        /**
         * Fetch security class ID of password before opening popup
         *
         * @returns {Promise}
         */
        $getPasswordId: function () {
            var self = this;

            return Passwords.getSecurityClassId(
                self.getAttribute('passwordId')
            ).then(function (securityClassId) {
                self.setAttribute(
                    'securityClassId',
                    securityClassId
                );
            });
        },

        /**
         * Get auth plugin information
         */
        $onLoaded: function () {
            var self = this;

            Passwords.getAvailableAuthPluginsInfo(
                self.getAttribute('passwordId')
            ).then(function (AuthPluginsInfo) {
                for (var i = 0, len = self.$authPluginControls.length; i < len; i++) {
                    var authPluginId = self.$authPluginControls[i].getAttribute('authPluginId');

                    if (!(authPluginId in AuthPluginsInfo)) {
                        continue;
                    }

                    var authStatus    = AuthPluginsInfo[authPluginId];
                    var AuthPluginElm = self.$authPluginControls[i].getElm();

                    if (!authStatus) {
                        new Element('div', {
                            'class': 'pcsg-gpm-password-auth-warning',
                            html   : '<span>' + QUILocale.get(lg, 'controls.password.authenticate.warning.unsynced') + '</span>'
                        }).inject(
                            AuthPluginElm,
                            'top'
                        );
                    }
                }
            });
        }
    });
});
