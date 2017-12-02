/**
 * Authenticate for a single security class
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onLoaded
 * @event onAbort - on AuthPopup user close
 * @event onClose - on AuthPopup close
 * @event onSubmit [AuthData] - if the user submits the auth form
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate', [

    'qui/controls/Control',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'Locale',

    'package/pcsg/grouppasswordmanager/bin/Authentication',

    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate.css'

], function (QUIControl, QUIPopup, QUIButton, QUILocale, Authentication) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate',

        Binds: [
            '$onInject',
            '$submit',
            'open',
            'close',
            '$openPopup',
            'getAuthData',
            '$checkAuth'
        ],

        options: {
            authPluginIds: [], // IDs of authentication plugins to be authenticated with
            required     : false, // amount of required factors for authentication
            info         : QUILocale.get(lg, 'controls.auth.authenticate.info')
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject : this.$onInject,
                onDestroy: this.$onDestroy
            });

            this.$AuthPopup             = null;
            this.$AuthStatus            = {};
            this.$authPluginIds         = this.getAttribute('authPluginIds');
            this.$required              = this.getAttribute('required') || 99;
            this.$AuthPluginData        = {};
            this.$authPluginControlData = [];
            this.$AuthPluginControls    = {};
            this.$authenticatedIds      = [];
            this.$isSubmitting          = false;
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
         * Open authentication popup
         */
        open: function () {
            var self = this;

            Promise.all([
                Authentication.checkAuthStatus(this.$authPluginIds),
                Authentication.getAuthPluginControls(this.$authPluginIds)
            ]).then(function (result) {
                self.$AuthStatus            = result[0];
                self.$authPluginControlData = result[1];
                self.$openPopup();
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
                content    : '<div class="pcsg-gpm-auth-authenticate-info">' +
                '</div>' +
                '<div class="pcsg-gpm-auth-authenticate-plugins"></div>' +
                '<label class="pcsg-gpm-auth-authenticate-save-label">' +
                '<span>' +
                QUILocale.get(lg, 'controls.authenticate.popup.label.save.authdata') +
                '</span>' +
                '<input type="checkbox" class="pcsg-gpm-auth-authenticate-save-authdata">' +
                '</label>'
            });

            this.$AuthPopup = AuthPopup;

            AuthPopup.open();

            AuthPopup.addButton(new QUIButton({
                'class': 'btn-green',
                text   : QUILocale.get(lg, 'controls.authenticate.popup.btn.text'),
                alt    : QUILocale.get(lg, 'controls.authenticate.popup.btn'),
                title  : QUILocale.get(lg, 'controls.authenticate.popup.btn'),
                events : {
                    onClick: this.$submit
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

            var Content = AuthPopup.getContent();
            var InfoElm = Content.getElement('.pcsg-gpm-auth-authenticate-info');

            AuthPopup.Loader.show();

            // set popup html and texts
            InfoElm.set(
                'html',
                '<h1 class="pcsg-gpm-auth-authenticate-info-title">' +
                QUILocale.get(lg, 'controls.authenticate.popup.info.title') +
                '</h1>' +
                '<span class="pcsg-gpm-auth-authenticate-info-description">' +
                this.getAttribute('info') +
                '</span>'
            );

            self.$buildContent();
        },

        /**
         * Load authentication control for every AuthPlugin and display it
         * appropriately
         */
        $buildContent: function () {
            var self = this;

            var Content    = this.$AuthPopup.getContent();
            var PluginsElm = Content.getElement('.pcsg-gpm-auth-authenticate-plugins');

            var i, len;
            var paths         = [];
            var autoSaveCount = 0;

            this.$authPluginIds    = [];
            this.$authenticatedIds = [];

            for (i = 0, len = this.$authPluginControlData.length; i < len; i++) {
                var AuthPluginControl = this.$authPluginControlData[i];

                self.$AuthPluginData[AuthPluginControl.authPluginId] = AuthPluginControl;
                paths.push(AuthPluginControl.control);
                this.$authPluginIds.push(AuthPluginControl.authPluginId);

                if (AuthPluginControl.autosave) {
                    autoSaveCount++;
                }
            }

            // auto-check autosave option
            if (autoSaveCount >= self.$required) {
                this.$AuthPopup.getContent().getElement(
                    '.pcsg-gpm-auth-authenticate-save-authdata'
                ).checked = true;
            }

            this.$AuthPopup.Loader.show();

            PluginsElm.set('html', '');

            var ClickPasswordRecovery = function (event) {
                event.stop();
                self.showLoader();
                self.$openAuthDataRecovery(
                    event.target.getParent('.pcsg-gpm-auth-authenticate-plugins-plugin').get(
                        'data-authpluginid'
                    )
                ).then(function () {
                    self.hideLoader();
                }, function () {
                    self.hideLoader();
                });
            };

            // load auth plugins
            require(
                paths,
                function () {
                    var i, len;
                    var controls                  = arguments;
                    var FirstControl              = false;
                    var eligibleAuthPluginsLoaded = 0;

                    // build controls
                    for (i = 0, len = controls.length; i < len; i++) {
                        var authPluginId   = self.$authPluginIds[i];
                        var AuthPluginData = self.$AuthPluginData[authPluginId];
                        var authenticated  = false;

                        var PluginElm = new Element('div', {
                            'data-authpluginid': authPluginId,
                            'class'            : 'pcsg-gpm-auth-authenticate-plugins-plugin',
                            'html'             : '<h3>' + AuthPluginData.title + '</h3>' +
                            '<input type="text">' +
                            '<span class="pcsg-gpm-auth-authenticate-plugins-plugin-recoverauthdata pcsg-gpm__hidden">' +
                            QUILocale.get(lg, 'controls.auth.authenticate.recover_authdata') +
                            '</span>'
                        }).inject(PluginsElm);

                        var Control = new controls[i]({
                            authPluginId: authPluginId
                        });

                        self.$AuthPluginControls[authPluginId] = Control;

                        Control.addEvents({
                            onSubmit: self.$submit
                        });

                        Control.imports(PluginElm.getElement('input'));
                        Control.setAttribute('eligibleForAuth', true);

                        // add event to "Password reset"-Link
                        PluginElm.getElement(
                            '.pcsg-gpm-auth-authenticate-plugins-plugin-recoverauthdata'
                        ).addEvent('click', ClickPasswordRecovery);

                        // if the user is already authenticated for a specific plugin
                        // disable it and show a check icon
                        if (authPluginId in self.$AuthStatus.authPlugins) {
                            if (self.$AuthStatus.authPlugins[authPluginId]) {
                                Control.hide();

                                new Element('span', {
                                    'class'            : 'fa fa-check pcsg-gpm-auth-authenticate-check',
                                    'data-authpluginid': authPluginId
                                }).inject(PluginElm, 'top');

                                authenticated = true;
                                self.$authenticatedIds.push(authPluginId);
                            }
                        }

                        if (!AuthPluginData.registered) {
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

                        if (!FirstControl && !authenticated) {
                            FirstControl = Control;
                        }
                    }

                    // hide unnecessary controls
                    if (eligibleAuthPluginsLoaded > self.$required) {
                        var FuncShowAuthPlugin = function () {
                            var controlId = this.get('data-controlid');
                            var Control   = self.$AuthPluginControls[controlId];

                            Control.show();
                            Control.focus();

                            this.destroy();
                        };

                        var count = 0;

                        for (i = 0, len = self.$authPluginIds.length; i < len; i++) {
                            if (count++ < self.$required) {
                                continue;
                            }

                            var AuthPluginControl = self.$AuthPluginControls[self.$authPluginIds[i]];
                            var AuthPluginElm     = AuthPluginControl.getElm();

                            new Element('div', {
                                'class'         : 'pcsg-gpm-auth-authenticate-plugins-show',
                                'data-controlid': self.$authPluginIds[i],
                                'html'          : '<span class="fa fa-plus"></span>' +
                                '<span class="pcsg-gpm-auth-authenticate-plugins-show-title">' +
                                QUILocale.get(lg, 'controls.auth.authenticate.show_plugin') +
                                '</span>',
                                events          : {
                                    click: FuncShowAuthPlugin
                                }
                            }).inject(
                                AuthPluginElm.getParent()
                            );

                            AuthPluginControl.hide();
                        }

                        //FirstControl = self.$AuthPluginControls[
                        //    Object.keys(self.$AuthPluginControls)[0]
                        //    ];
                    }

                    if (FirstControl) {
                        FirstControl.focus();
                    }

                    self.$AuthPopup.Loader.hide();
                    self.fireEvent('loaded');
                }
            );
        },

        /**
         * Submit authentication data
         */
        $submit: function () {
            if (this.$isSubmitting) {
                return;
            }

            this.$isSubmitting = true;

            var self     = this;
            var AuthData = this.getAuthData();

            AuthData.sessioncache = this.$AuthPopup.getContent().getElement(
                '.pcsg-gpm-auth-authenticate-save-authdata'
            ).checked;

            this.$AuthPopup.Loader.show();

            this.$checkAuth().then(function (isAuthenticated) {
                self.$AuthPopup.Loader.hide();

                if (isAuthenticated) {
                    self.fireEvent('submit', [AuthData]);
                }

                self.$isSubmitting = false;
            });
        },

        /**
         * Checks if user is still authenticated for all necessary
         * authentication plugins
         *
         * @return {Promise}
         */
        $checkAuth: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                Authentication.checkAuthStatus(self.$authPluginIds).then(function (AuthStatus) {
                    var isAuthenticated = true;

                    for (var authPluginId in AuthStatus.authPlugins) {
                        if (!AuthStatus.authPlugins.hasOwnProperty(authPluginId)) {
                            continue;
                        }

                        if (self.$authenticatedIds.contains(authPluginId) &&
                            !AuthStatus.authPlugins[authPluginId]) {
                            self.$AuthPluginControls[authPluginId].show();

                            if (isAuthenticated) {
                                self.$AuthPluginControls[authPluginId].focus();

                                var CheckIcon = self.$AuthPopup.getContent().getElement(
                                    'span[data-authpluginid="' + authPluginId + '"]'
                                );

                                if (CheckIcon) {
                                    CheckIcon.destroy();
                                }
                            }

                            self.$authenticatedIds.erase(authPluginId);
                            isAuthenticated = false;
                        }
                    }

                    resolve(isAuthenticated);
                }, reject);
            });
        },

        /**
         * Displays option to recover authdata
         *
         * @param {Number} authPluginId
         */
        displayAuthDataRecoveryOption: function (authPluginId) {
            var PluginElm = this.$AuthPopup.getContent().getElement(
                'div[data-authpluginid="' + authPluginId + '"]'
            );

            if (!PluginElm) {
                return;
            }

            PluginElm.setStyle('border', '2px solid #ed1c24');

            var RecoverAuthDataElm = PluginElm.getElement(
                'span.pcsg-gpm-auth-authenticate-plugins-plugin-recoverauthdata'
            );

            RecoverAuthDataElm.removeClass('pcsg-gpm__hidden');
            RecoverAuthDataElm.setStyle('display', 'inline-block');
        },

        /**
         * Opens Authentication Panel with recovery sheet
         *
         * @param {Number} authPluginId
         * @return {Promise}
         */
        $openAuthDataRecovery: function (authPluginId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                require([
                    'package/pcsg/grouppasswordmanager/bin/controls/auth/Panel',
                    'utils/Panels'
                ], function (AuthPanelControl, QUIPanelUtils) {
                    QUIPanelUtils.openPanelInTasks(new AuthPanelControl()).then(function (Panel) {
                        Panel.recoverAuthData(authPluginId);
                        self.close();

                        resolve();
                    }, reject);
                });
            });
        },

        /**
         * Show Popup Loader
         */
        showLoader: function () {
            this.$AuthPopup.Loader.show();
        },

        /**
         * Hide Popup Loader
         */
        hideLoader: function () {
            this.$AuthPopup.Loader.hide();
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

            for (var id in this.$AuthPluginControls) {
                if (!this.$AuthPluginControls.hasOwnProperty(id)) {
                    continue;
                }

                AuthData[id] = this.$AuthPluginControls[id].getAuthData();
            }

            return AuthData;
        }
    });
});
