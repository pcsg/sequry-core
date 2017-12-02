/**
 * Window for authentication with multiple security classes
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onSubmit [AuthData, this]
 * @event onClose [this]
 * @event onAbort [this]
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/Authentication',

    'text!package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/MultiSecurityClassAuthWindow.css'

], function (QUI, QUIPopup, QUIButton, QUILocale, Mustache, Authentication, template) {
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
            '$setSecurityClassSuccess',
            '$checkAuth'
        ],

        options: {
            securityClassIds: [],   // IDs of all security classes the user should authenticate for
            title           : QUILocale.get(lg, 'auth.multisecurityclassauthwindow.title'),
            info            : false // info text that is shown in top section of popup
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                backgroundClosable: true,
                closeButton       : false,
                titleCloseButton  : true,
                maxWidth          : 500
            });

            this.addEvents({
                onCreate: this.$onCreate
            });

            this.$authSuccessCount       = 0;
            this.$authSuccessCountNeeded = 0;
            this.$Authenticated          = {};
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
                'class': 'btn-green',
                text   : QUILocale.get(lg, 'controls.authenticate.popup.btn.text'),
                alt    : QUILocale.get(lg, 'controls.authenticate.popup.btn'),
                title  : QUILocale.get(lg, 'controls.authenticate.popup.btn'),
                events : {
                    onClick: function () {
                        self.fireEvent('submit', [self]);
                    }
                }
            });

            this.addButton(this.$AuthBtn);

            this.addButton(new QUIButton({
                text  : QUILocale.get(lg, 'controls.authenticate.popup.btn.abort.text'),
                alt   : QUILocale.get(lg, 'controls.authenticate.popup.btn.abort'),
                title : QUILocale.get(lg, 'controls.authenticate.popup.btn.abort'),
                events: {
                    onClick: function () {
                        self.fireEvent('abort');
                        self.close();
                    }
                }
            }));

            // add authenticate button that is only enabled
            // when user has authenticated with all SecurityClasses
            this.$AuthBtn.disable();

            // replace generic info with custom info
            var customInfo = this.getAttribute('info');
            var info       = QUILocale.get(lg, lg_prefix + 'info');

            if (customInfo) {
                info = customInfo;
            }

            this.setContent(
                Mustache.render(template, {
                    tableHeader: QUILocale.get(lg, lg_prefix + 'tableHeader'),
                    info       : info
                })
            );

            this.$Table = this.$Elm.getElement('table.multisecurityclassauth-data');

            this.Loader.show();

            self.$buildContent();
        },

        /**
         * (Re-)check authentication status for all SecurityClasses
         */
        $checkAuth: function () {
            var self = this;

            Authentication.checkSecurityClassAuthStatus(
                this.getAttribute('securityClassIds')
            ).then(function (AuthStatus) {
                self.Loader.hide();
                self.$authSuccessCount = 0;

                for (var securityClassId in AuthStatus) {
                    if (!AuthStatus.hasOwnProperty(securityClassId)) {
                        continue;
                    }

                    if (AuthStatus[securityClassId].authenticated) {
                        self.$setSecurityClassSuccess(securityClassId);
                    }
                }
            });
        },

        /**
         * Build control content
         */
        $buildContent: function () {
            var self = this;

            var securityClassIds         = this.getAttribute('securityClassIds');
            var securityClassInfosLoaded = 0;
            var TableBodyElm             = this.$Table.getElement('tbody');

            TableBodyElm.set('html', '');

            this.$authSuccessCountNeeded = securityClassIds.length;

            this.Loader.show();

            var FuncBuildSecurityClassElm = function (SecurityClassInfo) {
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
                    text           : QUILocale.get(lg,
                        'auth.multisecurityclassauthwindow.btn.unlock.text'
                    ),
                    alt            : QUILocale.get(lg,
                        'auth.multisecurityclassauthwindow.btn.unlock.text'
                    ),
                    title          : QUILocale.get(lg,
                        'auth.multisecurityclassauthwindow.btn.unlock.text'
                    ),
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
                    self.$checkAuth();
                }
            };

            for (var i = 0, len = securityClassIds.length; i < len; i++) {
                Authentication.getSecurityClassInfo(
                    securityClassIds[i]
                ).then(FuncBuildSecurityClassElm);
            }
        },

        /**
         * onClick Event for authentication button for a SecurityClass
         *
         * @param {Object} Btn
         */
        $onAuthBtnClick: function (Btn) {
            var self            = this;
            var securityClassId = Btn.getAttribute('securityClassId');

            this.Loader.show();

            Authentication.securityClassAuth(securityClassId).then(function () {
                self.Loader.hide();
                self.$checkAuth();
            }, function () {
                self.Loader.hide();
            });
        },

        /**
         * Set success status to SecurityClass info element
         *
         * @param {Integer} securityClassId
         */
        $setSecurityClassSuccess: function (securityClassId) {
            this.$authSuccessCount++;

            if (this.$authSuccessCount >= this.$authSuccessCountNeeded) {
                this.$AuthBtn.enable();
            }

            if (securityClassId in this.$Authenticated) {
                return;
            }

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

            this.$Authenticated[securityClassId] = true;
        }
    });
});
