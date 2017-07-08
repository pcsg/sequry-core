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
 * @event onSubmit [AuthData, this]
 * @event onClose [this]
 * @event onAbort [this]
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'qui/utils/Form',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/Authentication',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate',

    'Ajax',

    'text!package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow.css'

], function (QUI, QUIPopup, QUIButton, QUIFormUtils, QUILocale, Mustache,
             Authentication, AuthenticationControl, Ajax, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow',

        Binds: [
            '$onInject',
            '$onCreate',
            '$buildContent',
            '$onAuthBtnClick',
            '$setSecurityClassSuccess'
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
            this.$AuthStatus             = null;
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

            this.Loader.show();

            Authentication.checkAuthStatus(
                this.getAttribute('securityClassIds')
            ).then(function (AuthStatus) {
                self.$AuthStatus = AuthStatus;
                self.$buildContent();
                self.Loader.hide();
            });
        },

        /**
         * Build control content
         */
        $buildContent: function () {
            var self = this;

            var securityClassIds         = this.getAttribute('securityClassIds');
            var securityClassInfosLoaded = 0;
            var TableBodyElm             = self.$Table.getElement('tbody');

            self.$authSuccessCountNeeded = securityClassIds.length;

            this.Loader.show();

            var FuncBuildSecurityClassElm = function(SecurityClassInfo) {
                var SecurityClassElm = new Element('tr', {
                    'data-sid': SecurityClassInfo.id,
                    html      : '<td>' +
                    '<label class="field-container">' +
                    '<span class="field-container-item">' +
                    SecurityClassInfo.title +
                    '</span>' +
                    '<span class="field-container-field pcsg-gpm-auth-syncauthplugin-btn">' +
                    '</span>' +
                    '</label>' +
                    '</td>'
                }).inject(TableBodyElm);

                new QUIButton({
                    'class'        : 'pcsg-gpm-auth-btn-control',
                    textimage      : 'fa fa-lock',
                    text           : QUILocale.get(lg, 'auth.multisecurityclassauthwindow.btn.unlock.text'),
                    alt            : QUILocale.get(lg, 'auth.multisecurityclassauthwindow.btn.unlock.text'),
                    title          : QUILocale.get(lg, 'auth.multisecurityclassauthwindow.btn.unlock.text'),
                    securityClassId: SecurityClassInfo.id,
                    events         : {
                        onClick: self.$onAuthBtnClick
                    }
                }).inject(
                    SecurityClassElm.getElement('.pcsg-gpm-auth-syncauthplugin-btn')
                );

                securityClassInfosLoaded++;

                if (securityClassInfosLoaded >= securityClassIds.length) {
                    self.Loader.hide();
                }

                if (self.$AuthStatus[SecurityClassInfo.id].authenticated) {
                    self.$authSuccessCount++;
                    self.$setSecurityClassSuccess(SecurityClassInfo.id);
                }
            };

            for (var i = 0, len = securityClassIds.length; i < len; i++) {
                Authentication.getSecurityClassInfo(
                    securityClassIds[i]
                ).then(FuncBuildSecurityClassElm);
            }
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

            Authentication.securityClassAuth(securityClassId).then(function() {
                self.Loader.hide();
                self.$authSuccessCount++;
                self.$setSecurityClassSuccess(securityClassId);

                if (self.$authSuccessCount >= self.$authSuccessCountNeeded) {
                    self.$AuthBtn.enable();
                }
            }, function() {
                self.Loader.hide();
            });
        },

        /**
         * Set success status to SecurityClass info element
         *
         * @param {Integer} securityClassId
         */
        $setSecurityClassSuccess: function (securityClassId) {
            var Row = this.$Elm.getElement(
                'tr[data-sid="' + securityClassId + '"]'
            );

            var Btn = QUI.Controls.getById(
                Row.getElement(
                    '.pcsg-gpm-auth-btn-control'
                ).get('data-quiid')
            );

            Btn.setAttribute('textimage', 'fa fa-unlock');
            Btn.disable();

            new Element('span', {
                'class': 'fa fa-check auth-success-icon'
            }).inject(Btn.getElm(), 'after');
        }
    });
});
