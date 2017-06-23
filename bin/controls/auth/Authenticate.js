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

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',
    'package/pcsg/grouppasswordmanager/bin/classes/Actors',

    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate.css'

], function (QUI, QUIControl, QUIPopup, QUIButton, QUILocale, AuthHandler, ActorHandler) {
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
            'close',
            '$openPopup',
            'getAuthData'
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

            this.$AuthPopup     = null;
            this.$SecurityClass = null;
            this.$AuthStatus    = {};
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
                var securityClassId = self.getAttribute('securityClassId');

                Authentication.checkAuthStatus(
                    securityClassId
                ).then(function (StatusData) {
                    self.$AuthStatus = StatusData;
                    self.$openPopup();
                });

                //Authentication.isAuthenticatedBySession(
                //    securityClassId
                //).then(function (isAuth) {
                //    if (isAuth) {
                //        self.fireEvent('submit', [{}]);
                //    } else {
                //        self.$openPopup();
                //    }
                //});
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
                '<div class="pcsg-gpm-auth-authenticate-plugins"></div>' +
                '<label class="pcsg-gpm-auth-authenticate-save-label">' +
                '<span>' +
                QUILocale.get(lg, 'controls.authenticate.popup.label.save.authdata') +
                '</span>' +
                '<input type="checkbox" class="pcsg-gpm-auth-authenticate-save-authdata">' +
                '</label>'
            });

            this.$AuthPopup = AuthPopup;

            var submitFunc = function () {
                var AuthData = self.getAuthData();

                AuthData.sessioncache = AuthPopup.getContent().getElement(
                    '.pcsg-gpm-auth-authenticate-save-authdata'
                ).checked;

                self.fireEvent('submit', [AuthData]);
            };

            AuthPopup.open();

            AuthPopup.addButton(new QUIButton({
                'class': 'btn-green',
                text   : QUILocale.get(lg, 'controls.authenticate.popup.btn.text'),
                alt    : QUILocale.get(lg, 'controls.authenticate.popup.btn'),
                title  : QUILocale.get(lg, 'controls.authenticate.popup.btn'),
                events : {
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
                var authPluginControls = result[1];

                self.$SecurityClass = SecurityClassInfo;

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

                // auto-check autosave option
                if (autoSaveCount >= self.$SecurityClass.requiredFactors) {
                    AuthPopup.getContent().getElement(
                        '.pcsg-gpm-auth-authenticate-save-authdata'
                    ).checked = true;
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
                            var authPluginId = self.$authPluginIds[i];

                            var PluginElm = new Element('div', {
                                'class': 'pcsg-gpm-auth-authenticate-plugins-plugin'
                            }).inject(PluginsElm);

                            // if the user is already authenticated for a specific plugin
                            // do not build control
                            if (authPluginId in self.$AuthStatus) {
                                if (self.$AuthStatus[authPluginId]) {
                                    new Element('div', {
                                        html: 'Bereits authentifiziert!'
                                    }).inject(PluginElm);

                                    continue;
                                }
                            }

                            var Control = new controls[i]({
                                authPluginId: authPluginId
                            });

                            self.$authPluginControls.push(Control);

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

                        // hide unnecessary controls
                        if (eligibleAuthPluginsLoaded >= self.$SecurityClass.requiredFactors) {
                            for (i = 0, len = self.$authPluginControls.length; i < len; i++) {
                                if (i < self.$SecurityClass.requiredFactors) {
                                    continue;
                                }

                                var AuthPluginElm = self.$authPluginControls[i].getElm();

                                new Element('div', {
                                    'class'         : 'pcsg-gpm-auth-authenticate-plugins-show',
                                    'data-controlid': i,
                                    'html'          : '<span class="fa fa-plus"></span>' +
                                    '<span class="pcsg-gpm-auth-authenticate-plugins-show-title">' +
                                    authPluginControls[i].title +
                                    '</span>',
                                    events          : {
                                        click: function () {
                                            var controlId = this.getProperty('data-controlid');
                                            self.$authPluginControls[controlId].getElm().setStyle('display', '');

                                            this.destroy();
                                        }
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
