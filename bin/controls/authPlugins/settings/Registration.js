/**
 * Auth plugin registration setting (auth_plugins.registration)
 *
 * @module package/sequry/core/bin/controls/authPlugins/settings/Registration
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onSubmit [authData, self] - fires if the user submits the authentication data
 */
define('package/sequry/core/bin/controls/authPlugins/settings/Registration', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',

    'Locale',
    'Mustache',

    'package/sequry/core/bin/Authentication',

    'text!package/sequry/core/bin/controls/authPlugins/settings/Registration.Plugin.html',
    'css!package/sequry/core/bin/controls/authPlugins/settings/Registration.css'

], function (QUIControl, QUILoader, QUILocale, Mustache, Authentication, template) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/sequry/core/bin/controls/authPlugins/settings/Registration',

        Binds: [
            '$onImport',
            '$getEntry',
            '$writeSettings',
            '$readSettings'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Input    = null;
            this.Loader    = new QUILoader();
            this.$Content  = null;
            this.$Settings = {};

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * Event: onImport
         */
        $onImport: function () {
            var self = this;

            this.$Input      = this.getElm();
            this.$Input.type = 'hidden';

            if (this.$Input.value !== '') {
                this.$Settings = JSON.decode(this.$Input.value);
            }

            this.$Content = new Element('div', {
                'class': 'sequry-core-authplugins-settings-registration field-container-field'
            }).inject(this.$Input, 'after');

            this.Loader.inject(this.$Content);

            this.Loader.show();

            Promise.all([
                Authentication.getAuthPlugins(),
                Authentication.getDefaultAuthPluginId()
            ]).then(function (result) {
                var authPlugins     = result[0],
                    defaultPluginId = result[1];

                for (var i = 0, len = authPlugins.length; i < len; i++) {
                    if (parseInt(authPlugins[i].id) === parseInt(defaultPluginId)) {
                        continue;
                    }

                    self.$getEntry(authPlugins[i]).inject(self.$Content);
                }

                self.$readSettings();
                self.Loader.hide();
            });
        },

        $getEntry: function (AuthPlugin) {
            var lgPrefix = 'controls.authPlugins.settings.Registration.template.';

            var Entry = new Element('div', {
                'class'  : 'sequry-core-authplugins-settings-registration-plugin',
                'data-id': AuthPlugin.id,
                html     : Mustache.render(template, {
                    title                             : AuthPlugin.title,
                    optionNoPrompt                    : QUILocale.get(lg, lgPrefix + 'optionNoPrompt'),
                    optionPromptIfRequired            : QUILocale.get(lg, lgPrefix + 'optionPromptIfRequired'),
                    optionPromptIfRequiredRegistration: QUILocale.get(lg, lgPrefix + 'optionPromptIfRequiredRegistration'),
                    optionPromptAlways                : QUILocale.get(lg, lgPrefix + 'optionPromptAlways'),
                    optionPromptAlwaysRegistration    : QUILocale.get(lg, lgPrefix + 'optionPromptAlwaysRegistration')
                })
            });

            Entry.getElement('select').addEvent('change', this.$writeSettings);

            return Entry;
        },

        /**
         * Read settings for all auth plugins and write to this.$Input
         */
        $writeSettings: function () {
            console.log(1);

            var entries = this.$Content.getElements(
                '.sequry-core-authplugins-settings-registration-plugin'
            );

            for (var i = 0, len = entries.length; i < len; i++) {
                var Entry        = entries[i],
                    authPluginId = Entry.get('data-id'),
                    Select       = Entry.getElement('select');

                this.$Settings[authPluginId] = Select.value;
            }

            this.$Input.value = JSON.encode(this.$Settings);
        },

        /**
         * Read current settings and set them to all selects
         */
        $readSettings: function () {
            for (var authPluginId in this.$Settings) {
                if (!this.$Settings.hasOwnProperty(authPluginId)) {
                    continue;
                }

                var Entry = this.$Content.getElement(
                    '.sequry-core-authplugins-settings-registration-plugin[data-id="' + authPluginId + '"]'
                );

                if (!Entry) {
                    continue;
                }

                Entry.getElement('select').value = this.$Settings[authPluginId];
            }
        }
    });
});
