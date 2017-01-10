/**
 * User-specific settings for authentication plugins
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/user/AuthPluginSettings
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Mustache
 */
define('package/pcsg/grouppasswordmanager/bin/controls/user/AuthPluginSettings', [

    'qui/controls/Control',
    'qui/controls/buttons/Button',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',

    'Ajax',
    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/user/AuthPluginSettings.css'

], function (QUIControl, QUIButton, AuthenticationHandler, QUIAjax, QUILocale) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager';
    var Authentication = new AuthenticationHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/user/AuthPluginSettings',

        Binds: [
            '$onInject',
            '$setValuesToInput',
            '$setDataToInputs'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$SendBtn = null;
            this.$User    = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = new Element('div', {
                'class': 'pcsg-gpm-user-authpluginsettings',
                html   : ''
            });

            return this.$Elm;
        },

        /**
         * event: onImport
         */
        $onImport: function () {
            var self = this;

            this.$Input      = this.getElm();
            this.$Input.type = 'hidden';

            var Elm = this.create();
            Elm.inject(this.$Input, 'after');

            Authentication.getAuthPlugins().then(function (authPlugins) {
                for (var i = 0, len = authPlugins.length; i < len; i++) {
                    self.$getAuthPluginEntry(authPlugins[i]).inject(Elm);
                }

                if (self.$Input.value !== '') {
                    self.$setDataToInputs(JSON.decode(self.$Input.value));
                }
            });
        },

        /**
         * Get settings element for a single auth plugin
         *
         * @param {Object} AuthPlugin - AuthPlugin data
         * @return {HTMLDivElement}
         */
        $getAuthPluginEntry: function (AuthPlugin) {
            var AuthPluginElm = new Element('div', {
                'class'  : 'pcsg-gpm-user-authpluginsettings-plugin',
                'data-id': AuthPlugin.id,
                html     : '<h3>' + AuthPlugin.title + '</h3>' +
                '<div class="pcsg-gpm-user-authpluginsettings-plugin-options"></div>'
            });

            var OptionsElm = AuthPluginElm.getElement(
                '.pcsg-gpm-user-authpluginsettings-plugin-options'
            );

            // autosave
            var Label = new Element('label', {
                html: '<span>' +
                QUILocale.get(lg, 'controls.user.authpluginsettings.label.autosave') +
                '</span>'
            }).inject(OptionsElm);

            new Element('input', {
                'class': 'pcsg-gpm-user-authpluginsettings-plugin-options-option-autosave',
                type   : 'checkbox',
                events : {
                    change: this.$setValuesToInput
                }
            }).inject(Label);

            // priority
            Label = new Element('label', {
                html: '<span>' +
                QUILocale.get(lg, 'controls.user.authpluginsettings.label.priority') +
                '</span>'
            }).inject(OptionsElm);

            new Element('input', {
                'class': 'pcsg-gpm-user-authpluginsettings-plugin-options-option-priority',
                type   : 'number',
                min    : '1',
                max    : '100',
                value  : 1,
                events : {
                    change: this.$setValuesToInput
                }
            }).inject(Label);

            return AuthPluginElm;
        },

        /**
         * Set settings data to inputs
         *
         * @param {array} authPluginSettings
         */
        $setDataToInputs: function (authPluginSettings) {
            for (var i = 0, len = authPluginSettings.length; i < len; i++) {
                var Settings      = authPluginSettings[i];
                var AuthPluginElm = this.$Elm.getElement(
                    '.pcsg-gpm-user-authpluginsettings-plugin[data-id="' + Settings.id + '"]'
                );

                AuthPluginElm.getElement(
                    '.pcsg-gpm-user-authpluginsettings-plugin-options-option-autosave'
                ).checked = Settings.autosave;

                AuthPluginElm.getElement(
                    '.pcsg-gpm-user-authpluginsettings-plugin-options-option-priority'
                ).value = Settings.priority;
            }
        },

        /**
         * Set value to input
         */
        $setValuesToInput: function () {
            var authPluginSettings = [];
            var authPluginElms     = this.$Elm.getElements(
                '.pcsg-gpm-user-authpluginsettings-plugin'
            );

            for (var i = 0, len = authPluginElms.length; i < len; i++) {
                var AuthPluginElm = authPluginElms[i];

                var autosave = AuthPluginElm.getElement(
                    '.pcsg-gpm-user-authpluginsettings-plugin-options-option-autosave'
                ).checked;

                var priority = AuthPluginElm.getElement(
                    '.pcsg-gpm-user-authpluginsettings-plugin-options-option-priority'
                ).value;

                authPluginSettings.push({
                    id      : AuthPluginElm.getProperty('data-id'),
                    autosave: autosave,
                    priority: priority ? priority : false
                });
            }

            this.$Input.value = JSON.encode(authPluginSettings);
        }
    });
});
