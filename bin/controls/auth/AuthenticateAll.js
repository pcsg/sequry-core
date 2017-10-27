/**
 * Control for authenticating for all available auth plugins
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/AuthenticateAll
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/auth/AuthenticateAll.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/auth/AuthenticateAll.css
 *
 * @event onLoaded
 * @event onAbort - on AuthPopup user close
 * @event onClose - on AuthPopup close
 * @event onSubmit [AuthData] - if the user submits the auth form
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/AuthenticateAll', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'Locale',

    'package/pcsg/grouppasswordmanager/bin/Authentication'

    //'css!package/pcsg/grouppasswordmanager/bin/controls/auth/AuthenticateAll.css'

], function (QUI, QUIControl, QUIPopup, QUIButton, QUILocale, Authentication) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/auth/AuthenticateAll',

        Binds: [
            '$onInject',
            'submit',
            'open',
            'close',
            '$openPopup',
            'getAuthData'
        ],

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
            this.$Elm = this.parent();

            return this.$Elm;
        },

        /**
         * Open authentication popup and check if a Promise has to be resolved beforehand
         */
        open: function () {
            var self = this,
                Prom = Promise.resolve();

            Prom.then(function () {
                self.$openPopup();
            }).catch(function () {
                self.destroy();
            });
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

            var Content    = AuthPopup.getContent();
            var InfoElm    = Content.getElement('.pcsg-gpm-auth-authenticate-info');
            var PluginsElm = Content.getElement('.pcsg-gpm-auth-authenticate-plugins');

            AuthPopup.Loader.show();

            this.$authPluginControls = [];
            this.$authPluginIds      = [];

            Authentication.getControlsByUser().then(function (authPluginControls) {
                // set popup html and texts
                InfoElm.set(
                    'html',
                    '<h1 class="pcsg-gpm-auth-authenticate-info-title">' +
                    QUILocale.get(lg, 'controls.authenticate.popup.info.title') +
                    '</h1>'
                );

                var i, len;
                var paths                 = [];
                var AuthPluginsRegistered = {};
                var autoSaveCount         = 0;

                for (i = 0, len = authPluginControls.length; i < len; i++) {
                    var AuthPluginControl = authPluginControls[i];

                    self.$authPluginIds.push(AuthPluginControl.authPluginId);
                    AuthPluginsRegistered[AuthPluginControl.authPluginId] = AuthPluginControl.registered;
                    paths.push(AuthPluginControl.control);

                    if (AuthPluginControl.autosave) {
                        autoSaveCount++;
                    }
                }

                // load auth plugins
                require(
                    paths,
                    function () {
                        var controls                  = arguments;
                        var FirstControl              = false;
                        var eligibleAuthPluginsLoaded = 0;

                        // build controls
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
                            Control.setAttribute('eligibleForAuth', true);

                            if (!AuthPluginsRegistered[self.$authPluginIds[i]]) {
                                new Element('div', {
                                    'class': 'pcsg-gpm-auth-authenticate-warning',
                                    html   : '<span>' +
                                    QUILocale.get(
                                        lg,
                                        'controls.auth.authenticate.warning.nonregistered'
                                    ) +
                                    '</span>'
                                }).inject(
                                    PluginElm,
                                    'top'
                                );

                                Control.setAttribute('eligibleForAuth', false);
                            } else {
                                eligibleAuthPluginsLoaded++;
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
         * Close popup
         */
        close: function () {
            if (this.$AuthPopup) {
                this.$AuthPopup.close();
            }
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
