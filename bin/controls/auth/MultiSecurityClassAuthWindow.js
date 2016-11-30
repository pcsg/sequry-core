/**
 * Window for authentication with multiple security classes
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow.css
 *
 * @event onSuccess [this]
 * @event onFail [this]
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'qui/utils/Form',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate',

    'Ajax',

    'text!package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow.css'

], function (QUI, QUIPopup, QUIButton, QUIFormUtils, QUILocale, Mustache,
             AuthHandler, AuthenticationControl, Ajax, template) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager',
        Authentication = new AuthHandler();

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow',

        Binds: [
            '$onInject',
            'submit',
            '$showRecovery',
            '$print',
            '$buildContent',
            '$startSync'
        ],

        options: {
            securityClassIds: [],   // id of all security classes the user should authenticate for
            title           : QUILocale.get(lg, 'auth.multisecurityclassauthwindow.title')
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
            this.$Table                  = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        $onCreate: function () {
            var self      = this;
            var lg_prefix = 'auth.multisecurityclassauthwindow.';

            this.$Elm.addClass('pcsg-gpm-multisecurityclassauth');

            this.$AuthBtn = new QUIButton({
                textimage: 'fa fa-key',
                text     : QUILocale.get(lg, 'auth.multisecurityclassauthwindow.btn.auth.text'),
                alt      : QUILocale.get(lg, 'auth.multisecurityclassauthwindow.btn.auth'),
                title    : QUILocale.get(lg, 'auth.multisecurityclassauthwindow.btn.auth'),
                events   : {
                    onClick: function () {
                        self.fireEvent('submit', [self.$AuthData, self]);
                        self.$AuthData = {};
                    }
                }
            });

            this.addButton(this.$AuthBtn);
            this.$AuthBtn.disable();

            this.setContent(
                Mustache.render(template, {
                    tableHeader: QUILocale.get(lg, lg_prefix + 'tableHeader'),
                    info       : QUILocale.get(lg, lg_prefix + 'info')
                })
            );

            this.$Table = this.$Elm.getElement('table.multisecurityclassauth-data');
            this.$buildContent();
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
                                                'auth.multisecurityclassauthwindow.authdata.incorrect', {
                                                    securityClassId: securityClassId
                                                }
                                            )
                                        );
                                    });

                                    return;
                                }

                                self.$AuthData[securityClassId] = AuthData;
                                Btn.setAttribute('textimage', 'fa fa-unlock');
                                Btn.disable();

                                new Element('span', {
                                    'class': 'fa fa-check auth-success-icon'
                                }).inject(Btn.getElm(), 'after');

                                self.$authSuccessCount++;

                                if (self.$authSuccessCount >= self.$authSuccessCountNeeded) {
                                    self.$AuthBtn.enable();
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

            var securityClassIds         = this.getAttribute('securityClassIds');
            var securityClassInfosLoaded = 0;
            var TableBodyElm             = self.$Table.getElement('tbody');

            self.$authSuccessCountNeeded = securityClassIds.length;

            this.Loader.show();

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
                        text           : QUILocale.get(lg, 'auth.multisecurityclassauthwindow.btn.unlock.text'),
                        alt            : QUILocale.get(lg, 'auth.multisecurityclassauthwindow.btn.unlock.text'),
                        title          : QUILocale.get(lg, 'auth.multisecurityclassauthwindow.btn.unlock.text'),
                        securityClassId: SecurityClassInfo.id,
                        events         : {
                            onClick: FuncOnAuthBtnClick
                        }
                    }).inject(
                        SecurityClassElm.getElement('.pcsg-gpm-auth-syncauthplugin-btn')
                    );

                    securityClassInfosLoaded++;

                    if (securityClassInfosLoaded >= securityClassIds.length) {
                        self.Loader.hide();
                    }
                });
            }
        }
    });
});
