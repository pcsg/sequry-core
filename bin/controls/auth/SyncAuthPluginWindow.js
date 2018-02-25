/**
 * Control for managing synchronization of authentication plugins
 *
 * @module package/sequry/core/bin/controls/auth/SyncAuthPluginWindow
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Locale
 * @require Mustache
 * @require package/sequry/core/bin/controls/securityclasses/Select
 * @require text!package/sequry/core/bin/controls/auth/SyncAuthPluginWindow.html
 * @require css!package/sequry/core/bin/controls/auth/SyncAuthPluginWindow.css
 *
 * @event onSuccess [this]
 * @event onFail [this]
 */
define('package/sequry/core/bin/controls/auth/SyncAuthPluginWindow', [

    'package/sequry/core/bin/controls/auth/MultiSecurityClassAuthWindow',
    'package/sequry/core/bin/Authentication',
    'Locale',

    'Ajax'

], function (MultiSecurityClassAuthWindow, Authentication, QUILocale, QUIAjax) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: MultiSecurityClassAuthWindow,
        Type   : 'package/sequry/core/bin/controls/auth/SyncAuthPluginWindow',

        Binds: [
            '$restrictedSecurityClassAuth',
            '$startSync'
        ],

        options: {
            authPluginId: false,   // id of auth plugin that is to be synced
            title       : QUILocale.get(lg, 'auth.syncauthpluginwindow.title'),
            info        : QUILocale.get(lg, 'auth.syncauthpluginwindow.info')
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onSubmit: this.$startSync
            });
        },

        /**
         * onClick Event on authentication button for a SecurityClass
         *
         * @param {Object} Btn
         */
        $onAuthBtnClick: function (Btn) {
            var self            = this;
            var securityClassId = Btn.getAttribute('securityClassId');

            this.Loader.show();

            this.$restrictedSecurityClassAuth(securityClassId).then(function () {
                self.Loader.hide();
                self.$authSuccessCount++;
                self.$setSecurityClassSuccess(securityClassId);

                if (self.$authSuccessCount >= self.$authSuccessCountNeeded) {
                    self.$AuthBtn.enable();
                }
            }, function () {
                self.Loader.hide();
            });
        },

        /**
         * Authenticate for a SecurityClass where certain authentication
         * plugins are disabled
         *
         * @param {Number} securityClassId
         * @return {Promise}
         */
        $restrictedSecurityClassAuth: function (securityClassId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                require([
                    'package/sequry/core/bin/controls/auth/SyncAuthenticate'
                ], function (SyncAuth) {
                    var Popup = new SyncAuth({
                        authPluginId   : self.getAttribute('authPluginId'),
                        securityClassId: securityClassId,
                        events         : {
                            onSubmit: function (AuthData) {
                                Authentication.$authenticate(
                                    securityClassId,
                                    AuthData
                                ).then(function (success) {
                                    if (!success) {
                                        return;
                                    }

                                    Popup.close();
                                    resolve();
                                }, function () {
                                    // do nothing if auth data is wrong
                                });
                            },
                            onClose : function () {
                                reject();
                                Popup.close();
                            },
                            onAbort : function () {
                                reject();
                                Popup.close();
                            }
                        }
                    });

                    Popup.open();
                });
            });
        },

        /**
         * Start authentication plugin synchronisations
         */
        $startSync: function () {
            var self = this;

            QUIAjax.post(
                'package_sequry_core_ajax_auth_syncAuthPlugin',
                function (success) {
                    self.close();

                    if (!success) {
                        self.fireEvent('fail', [self]);
                        return;
                    }

                    self.fireEvent('success', [self]);
                }, {
                    'package'   : 'sequry/core',
                    authPluginId: self.getAttribute('authPluginId')
                }
            );
        }
    });
});
