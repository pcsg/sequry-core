/**
 * Control for creating a new password
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate.css
 *
 * @event onLoaded
 * @event onAbort - on AuthPopup user close
 * @event onClose - on AuthPopup close
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'Locale',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',

    'Ajax',

    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate.css'

], function (QUI, QUIControl, QUIPopup, QUIButton, QUILocale, AuthHandler, Ajax) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager',
        Authentication = new AuthHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate',

        Binds: [
            '$onInject',
            'submit',
            'open',
            '$openPopup'
        ],

        options: {
            'securityClassId': false, // id the security class the authentication is for
            'beforeOpen'     : false // Promise Object that is resolved before the popup opens
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject : this.$onInject,
                onDestroy: this.$onDestroy
            });

            this.$AuthPopup = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            return this.parent();
        },

        /**
         * event : oninject
         */
        $onInject: function () {
            // @todo
        },

        /**
         * event: ondestroy
         */
        $onDestroy: function () {
            if (this.$AuthPopup) {
                this.$AuthPopup.close();
            }
        },

        /**
         * Open authentication popup and check if a Promise has to be resolved beforehand
         */
        open: function () {
            var self = this,
                Prom = Promise.resolve();

            if (this.getAttribute('beforeOpen')) {
                Prom = this.getAttribute('beforeOpen')();
            }

            Prom.then(function () {
                self.$openPopup();
            }).catch(function () {
                self.destroy();
            });
        },

        /**
         * Open popup
         */
        $openPopup: function () {
            var self = this;

            var AuthPopup = new QUIPopup({
                title      : QUILocale.get(
                    lg, 'controls.authenticate.popup.title'
                ),
                maxWidth   : 500,
                closeButton: false,
                events     : {
                    onClose: function () {
                        self.fireEvent('close');
                    }
                },
                content    : '<div class="pcsg-gpm-auth-authenticate-info"></div>' +
                '<div class="pcsg-gpm-auth-authenticate-plugins"></div>'
            });

            this.$AuthPopup = AuthPopup;

            var submitFunc = function () {
                self.fireEvent('submit', [self.getAuthData()]);
            };

            AuthPopup.open();

            AuthPopup.addButton(new QUIButton({
                text  : QUILocale.get(lg, 'controls.authenticate.popup.btn.text'),
                alt   : QUILocale.get(lg, 'controls.authenticate.popup.btn'),
                title : QUILocale.get(lg, 'controls.authenticate.popup.btn'),
                events: {
                    onClick: submitFunc
                }
            }));

            AuthPopup.addButton(new QUIButton({
                text  : QUILocale.get(lg, 'controls.authenticate.popup.btn.abort.text'),
                alt   : QUILocale.get(lg, 'controls.authenticate.popup.btn.abort'),
                title : QUILocale.get(lg, 'controls.authenticate.popup.btn.abort'),
                events: {
                    onClick: function () {
                        self.fireEvent('abort');
                        AuthPopup.close();
                    }
                }
            }));

            var Content         = AuthPopup.getContent();
            var InfoElm         = Content.getElement('.pcsg-gpm-auth-authenticate-info');
            var PluginsElm      = Content.getElement('.pcsg-gpm-auth-authenticate-plugins');
            var securityClassId = this.getAttribute('securityClassId');

            AuthPopup.Loader.show();

            this.$authPluginControls = [];
            this.$authPluginIds      = [];

            Promise.all([
                Authentication.getSecurityClassInfo(securityClassId),
                Authentication.getAuthPluginControlsBySecurityClass(securityClassId)
            ]).then(function (result) {
                var SecurityClassInfo  = result[0];
                var AuthPluginControls = result[1];

                // set popup html and texts
                InfoElm.set(
                    'html',
                    '<h1 class="pcsg-gpm-auth-authenticate-info-title">' +
                    QUILocale.get(lg, 'controls.authenticate.popup.info.title') +
                    '</h1>' +
                    '<span class="pcsg-gpm-auth-authenticate-info-description">' +
                    QUILocale.get(lg, 'controls.authenticate.popup.info.description', {
                        securityClass  : SecurityClassInfo.title,
                        requiredFactors: SecurityClassInfo.requiredFactors
                    }) +
                    '</span>'
                );

                var paths                 = [];
                var AuthPluginsRegistered = {};

                for (var authPluginId in AuthPluginControls) {
                    if (!AuthPluginControls.hasOwnProperty(authPluginId)) {
                        continue;
                    }

                    var AuthPluginControl = AuthPluginControls[authPluginId];

                    self.$authPluginIds.push(authPluginId);
                    AuthPluginsRegistered[authPluginId] = AuthPluginControl.registered;
                    paths.push(AuthPluginControl.control);
                }

                // load auth plugins
                require(
                    paths,
                    function () {
                        var controls     = arguments;
                        var FirstControl = false;

                        console.log(controls);

                        for (var i = 0, len = controls.length; i < len; i++) {
                            var Control = new controls[i]({
                                authPluginId: self.$authPluginIds[i]
                            });

                            self.$authPluginControls.push(Control);

                            var PluginElm = new Element('div', {
                                'class': 'pcsg-gpm-auth-authenticate-plugins-plugin'
                            }).inject(PluginsElm);

                            Control.addEvents({
                                onSubmit: submitFunc
                            });

                            Control.inject(PluginElm);

                            if (!AuthPluginsRegistered[self.$authPluginIds[i]]) {
                                new Element('div', {
                                    'class': 'pcsg-gpm-auth-authenticate-warning',
                                    html   : '<span>' + QUILocale.get(lg, 'controls.auth.authenticate.warning.nonregistered') + '</span>'
                                }).inject(
                                    PluginElm,
                                    'top'
                                );
                            }

                            if (!FirstControl) {
                                FirstControl = Control;
                            }
                        }

                        if (FirstControl) {
                            FirstControl.focus();
                        }

                        AuthPopup.Loader.hide();
                        self.fireEvent('loaded');
                    }
                );
            });
        },

        /**
         * Get authentication data
         *
         * @return {Object}
         */
        getAuthData: function () {
            var AuthData = {};

            for (var i = 0, len = this.$authPluginControls.length; i < len; i++) {
                var Control      = this.$authPluginControls[i];
                var authPluginId = Control.getAttribute('authPluginId');

                AuthData[authPluginId] = Control.getAuthData();
            }

            return AuthData;
        }
    });
});
