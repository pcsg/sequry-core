/**
 * Control for managing synchronization of authentication plugins
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/SyncAuthPluginWindow
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/auth/SyncAuthPluginWindow.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/auth/SyncAuthPluginWindow.css
 *
 * @event onSuccess [this]
 * @event onFail [this]
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/SyncAuthPluginWindow', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'qui/utils/Form',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/SyncAuthenticate',

    'Ajax',

    'text!package/pcsg/grouppasswordmanager/bin/controls/auth/SyncAuthPluginWindow.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/SyncAuthPluginWindow.css'

], function (QUI, QUIPopup, QUIButton, QUIFormUtils, QUILocale, Mustache,
             AuthHandler, AuthenticationControl, Ajax, template) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager',
        Authentication = new AuthHandler();

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/auth/SyncAuthPluginWindow',

        Binds: [
            '$onInject',
            'submit',
            '$showRecovery',
            '$print',
            '$buildContent',
            '$startSync'
        ],

        options: {
            'authPluginId': false,   // id of auth plugin that is to be synced
            title         : QUILocale.get(lg, 'auth.syncauthpluginwindow.title')
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                backgroundClosable: true,
                closeButton       : true,
                titleCloseButton  : true,
                maxWidth          : 500
            });

            this.addEvents({
                onCreate: this.$onCreate
            });

            this.$AuthData               = {}; // auth data per security class
            this.$authSuccessCount       = 0;
            this.$authSuccessCountNeeded = 0;
            this.$AuthPlugin             = null;
            this.$Table                  = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        $onCreate: function () {
            var self      = this;
            var lg_prefix = 'auth.syncauthpluginwindow.';

            this.$SyncBtn = new QUIButton({
                textimage: 'fa fa-refresh',
                text     : QUILocale.get(lg, 'auth.syncauthpluginwindow.btn.sync.text'),
                alt      : QUILocale.get(lg, 'auth.syncauthpluginwindow.btn.sync'),
                title    : QUILocale.get(lg, 'auth.syncauthpluginwindow.btn.sync'),
                events   : {
                    onClick: self.$startSync
                }
            });

            this.addButton(this.$SyncBtn);
            this.$SyncBtn.disable();

            Authentication.getAuthPluginInfo(
                this.getAttribute('authPluginId')
            ).then(function (AuthPluginData) {
                self.setContent(
                    Mustache.render(template, {
                        tableHeader: QUILocale.get(lg, lg_prefix + 'tableHeader'),
                        info       : QUILocale.get(lg, lg_prefix + 'info', {
                            authPluginTitle: AuthPluginData.title
                        })
                    })
                );

                self.$Table = self.getContent().getElement('table.securityclass-data');
                self.$buildContent();
            });
        },

        /**
         * Build control content
         */
        $buildContent: function () {
            var self = this;

            this.Loader.show();

            var FuncOnAuthBtnClick = function (Btn) {
                var securityClassId = Btn.getAttribute('securityClassId');

                self.Loader.show();

                var AuthControl = new AuthenticationControl({
                    securityClassId: securityClassId,
                    authPluginId   : self.getAttribute('authPluginId'),
                    events         : {
                        onSubmit: function (AuthData) {
                            Authentication.checkAuthInfo(
                                securityClassId,
                                AuthData
                            ).then(function (authDataCorrect) {
                                self.Loader.hide();
                                AuthControl.destroy();

                                if (!authDataCorrect) {
                                    QUI.getMessageHandler().then(function (MH) {
                                        MH.addError(
                                            QUILocale.get(
                                                lg,
                                                'auth.syncauthpluginwindow.authdata.incorrect'
                                            )
                                        );
                                    });

                                    return;
                                }

                                self.$AuthData[securityClassId] = AuthData;
                                Btn.setAttribute('textimage', 'fa fa-unlock');
                                Btn.disable();

                                new Element('span', {
                                    'class': 'fa fa-check  pcsg-gpm-auth-syncauthplugin-auth-success'
                                }).inject(Btn.getElm(), 'after');

                                self.$authSuccessCount++;

                                if (self.$authSuccessCount >= self.$authSuccessCountNeeded) {
                                    self.$SyncBtn.enable();
                                }
                            });
                        },
                        onAbort : function () {
                            self.Loader.hide();
                        },
                        onClose : function () {
                            self.fireEvent('close');
                        }
                    }
                });

                AuthControl.open();
            };

            Authentication.getNonFullyAccessiblePasswordSecurityClassIds(
                this.getAttribute('authPluginId')
            ).then(function (securityClassIds) {
                self.Loader.hide();

                if (!securityClassIds.length) {
                    self.close();
                    return;
                }

                self.$authSuccessCountNeeded = securityClassIds.length;

                var TableBodyElm = self.$Table.getElement('tbody');

                for (var i = 0, len = securityClassIds.length; i < len; i++) {
                    Authentication.getSecurityClassInfo(
                        securityClassIds[i]
                    ).then(function (SecurityClassInfo) {
                        var SecurityClassElm = new Element('tr', {
                            html: '<td>' +
                            '<label class="field-container">' +
                            '<span class="field-container-item">' +
                            SecurityClassInfo.title + ' (ID: ' + SecurityClassInfo.id + ')' +
                            '</span>' +
                            '<span class="field-container-field pcsg-gpm-auth-syncauthplugin-btn">' +
                            '</span>' +
                            '</label>' +
                            '</td>'
                        }).inject(TableBodyElm);

                        new QUIButton({
                            textimage      : 'fa fa-lock',
                            text           : QUILocale.get(lg, 'auth.syncauthpluginwindow.btn.unlock.text'),
                            alt            : QUILocale.get(lg, 'auth.syncauthpluginwindow.btn.unlock.text'),
                            title          : QUILocale.get(lg, 'auth.syncauthpluginwindow.btn.unlock.text'),
                            securityClassId: SecurityClassInfo.id,
                            events         : {
                                onClick: FuncOnAuthBtnClick
                            }
                        }).inject(
                            SecurityClassElm.getElement('.pcsg-gpm-auth-syncauthplugin-btn')
                        )
                    });
                }
            });
        },

        /**
         * Start authentication plugin synchronisations
         */
        $startSync: function () {
            var self = this;

            new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_syncAuthPlugin', resolve, {
                    'package'   : 'pcsg/grouppasswordmanager',
                    onError     : reject,
                    authPluginId: self.getAttribute('authPluginId'),
                    authData    : JSON.encode(self.$AuthData)
                });
            }).then(function (success) {
                self.$AuthData = null;
                self.close();

                if (!success) {
                    self.fireEvent('fail', [self]);
                    return;
                }

                self.fireEvent('success', [self]);
            });
        }
    });
});
