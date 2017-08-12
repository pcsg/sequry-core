/**
 * Authenticate for a single security class
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
 * @event onSubmit [AuthData] - if the user submits the auth form
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'Locale',

    'package/pcsg/grouppasswordmanager/bin/Authentication',

    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate.css'

], function (QUI, QUIControl, QUIPopup, QUIButton, QUILocale, Authentication) {
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
            'getAuthData'
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
            this.$authPluginControls    = [];
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

            var Content         = AuthPopup.getContent();
            var InfoElm         = Content.getElement('.pcsg-gpm-auth-authenticate-info');
            var securityClassId = this.getAttribute('securityClassId');

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

            var i, len;
            var paths         = [];
            var autoSaveCount = 0;

            for (i = 0, len = this.$authPluginControlData.length; i < len; i++) {
                var AuthPluginControl = this.$authPluginControlData[i];

                self.$AuthPluginData[AuthPluginControl.authPluginId] = AuthPluginControl;
                paths.push(AuthPluginControl.control);

                if (AuthPluginControl.autosave) {
                    autoSaveCount++;
                }
            }

            // auto-check autosave option
            if (autoSaveCount >= self.$required) {
                AuthPopup.getContent().getElement(
                    '.pcsg-gpm-auth-authenticate-save-authdata'
                ).checked = true;
            }

            self.$buildContent(paths);
        },

        /**
         * Load authentication control for every AuthPlugin and display it
         * appropriately
         *
         * @param {Array} controlPaths - require paths for authentication controls
         */
        $buildContent: function (controlPaths) {
            var self       = this;
            var Content    = this.$AuthPopup.getContent();
            var PluginsElm = Content.getElement('.pcsg-gpm-auth-authenticate-plugins');

            this.$AuthPopup.Loader.show();

            // load auth plugins
            require(
                controlPaths,
                function () {
                    var controls                  = arguments;
                    var FirstControl              = false;
                    var eligibleAuthPluginsLoaded = 0;

                    // build controls
                    for (var i = 0, len = controls.length; i < len; i++) {
                        var authPluginId   = self.$authPluginIds[i];
                        var AuthPluginData = self.$AuthPluginData[authPluginId];
                        var authenticated  = false;

                        var PluginElm = new Element('div', {
                            'class': 'pcsg-gpm-auth-authenticate-plugins-plugin',
                            'html' : '<h3>' + AuthPluginData.title + '</h3>' +
                            '<input type="text">'
                        }).inject(PluginsElm);

                        var Control = new controls[i]({
                            authPluginId: authPluginId
                        });

                        self.$authPluginControls.push(Control);

                        Control.addEvents({
                            onSubmit: self.$submit
                        });

                        Control.imports(PluginElm.getElement('input'));
                        Control.setAttribute('eligibleForAuth', true);

                        // if the user is already authenticated for a specific plugin
                        // disable it and show a check icon
                        if (authPluginId in self.$AuthStatus.authPlugins) {
                            if (self.$AuthStatus.authPlugins[authPluginId]) {
                                Control.disable();
                                Control.getElm().addClass(
                                    'pcsg-gpm-auth-authenticate-plugins-plugin__hidden'
                                );

                                new Element('span', {
                                    'class': 'fa fa-check pcsg-gpm-auth-authenticate-check'
                                }).inject(PluginElm, 'top');

                                authenticated = true;
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
                            var Control   = self.$authPluginControls[controlId];

                            Control.getElm().setStyle('display', '');
                            Control.focus();

                            this.destroy();
                        };

                        for (i = 0, len = self.$authPluginControls.length; i < len; i++) {
                            if (i < self.$required) {
                                continue;
                            }

                            var AuthPluginElm = self.$authPluginControls[i].getElm();

                            new Element('div', {
                                'class'         : 'pcsg-gpm-auth-authenticate-plugins-show',
                                'data-controlid': i,
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

                            AuthPluginElm.setStyle(
                                'display',
                                'none'
                            );
                        }

                        FirstControl = self.$authPluginControls[0];
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
            var AuthData = this.getAuthData();

            AuthData.sessioncache = this.$AuthPopup.getContent().getElement(
                '.pcsg-gpm-auth-authenticate-save-authdata'
            ).checked;

            this.fireEvent('submit', [AuthData]);
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
