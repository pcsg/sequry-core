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
 * @require package/pcsg/grouppasswordmanager/bin/controls/auth/SecurityClassSelect
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate.css
 *
 * @event onFinish
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
            'submit'
        ],

        options: {
            'securityClassId': false // id the security class the authentication is for
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.$AuthData = {};
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
         * event : on inject
         */
        $onInject: function () {
            // @todo
        },

        open: function () {
            var self = this;

            var AuthPopup = new QUIPopup({
                title      : QUILocale.get(
                    lg, 'controls.authenticate.popup.title'
                ),
                maxWidth   : 500,
                closeButton: true,
                content    : '<div class="pcsg-gpm-auth-authenticate-info"></div>' +
                '<div class="pcsg-gpm-auth-authenticate-plugins"></div>'
            });

            AuthPopup.open();

            AuthPopup.addButton(new QUIButton({
                text  : QUILocale.get(lg, 'controls.authenticate.popup.btn.text'),
                alt   : QUILocale.get(lg, 'controls.authenticate.popup.btn'),
                title : QUILocale.get(lg, 'controls.authenticate.popup.btn'),
                events: {
                    onClick: function () {
                        self.fireEvent('submit', [ self.getAuthData() ]);
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
                var SecurityClassInfo = result[0];
                var AuthPluginPaths = result[1];

                // set popup html and texts
                InfoElm.set(
                    'html',
                    '<h1 class="pcsg-gpm-auth-authenticate-info-title">' +
                    QUILocale.get(lg, 'controls.authenticate.popup.info.title') +
                    '</h1>' +
                    '<span class="pcsg-gpm-auth-authenticate-info-description">' +
                    QUILocale.get(lg, 'controls.authenticate.popup.info.description', {
                        securityClass: SecurityClassInfo.title
                    }) +
                    '</span>'
                );

                var paths = [];

                for (var authPluginId in AuthPluginPaths) {
                    if (!AuthPluginPaths.hasOwnProperty(authPluginId)) {
                        continue;
                    }

                    var authPluginPath = AuthPluginPaths[authPluginId];

                    self.$authPluginIds.push(authPluginId);
                    paths.push(authPluginPath);
                }

                // load auth plugins
                require(
                    paths,
                    function () {
                        var controls = arguments;

                        for (var i = 0, len = controls.length; i < len; i++) {
                            var Control = new controls[i]();

                            self.$authPluginControls.push(Control);

                            var PluginElm = new Element('div', {
                                'class': 'pcsg-gpm-auth-authenticate-plugins-plugin'
                            }).inject(PluginsElm);

                            Control.inject(PluginElm);
                        }

                        AuthPopup.Loader.hide();
                    }
                );
            });
        },

        /**
         * Get authentication data
         *
         * @return {{}}
         */
        getAuthData: function () {
            var AuthData = {};

            for (var i = 0, len = this.$authPluginIds; i < len; i++) {
                // not optimal
                AuthData[this.$authPluginIds[i]] = this.$authPluginControls[i].getAuthData();
            }

            return AuthData;
        },

        /**
         * Authenticate current user with plugin
         *
         * @returns {Promise}
         */
        submit: function () {

        }
    });
});
