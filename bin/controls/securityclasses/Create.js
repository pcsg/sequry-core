/**
 * Control for creating a new password
 *
 * @module package/sequry/core/bin/controls/securityclasses/Create
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onLoaded
 * @event onSuccess
 */
define('package/sequry/core/bin/controls/securityclasses/Create', [

    'qui/controls/Control',
    'qui/controls/buttons/Select',
    'qui/utils/Form',

    'Locale',
    'Mustache',

    'package/sequry/core/bin/Authentication',

    'text!package/sequry/core/bin/controls/securityclasses/Create.html',
    'css!package/sequry/core/bin/controls/securityclasses/Create.css'

], function (QUIControl, QUISelect, QUIFormUtils, QUILocale, Mustache,
             Authentication, template) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/sequry/core/bin/controls/securityclasses/Create',

        Binds: [
            '$onInject',
            '$refreshFactorSelect'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.$RequiredFactorsSelect = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            var self      = this,
                lg_prefix = 'securityclasses.create.template.';

            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-securityclasses-create',
                html   : Mustache.render(template, {
                    createtitle                      : QUILocale.get(lg, lg_prefix + 'createtitle'),
                    title                            : QUILocale.get(lg, lg_prefix + 'title'),
                    description                      : QUILocale.get(lg, lg_prefix + 'description'),
                    authPlugins                      : QUILocale.get(lg, lg_prefix + 'authPlugins'),
                    authPluginsWarning               : QUILocale.get(lg, lg_prefix + 'authPluginsWarning'),
                    groups                           : QUILocale.get(lg, lg_prefix + 'groups'),
                    requiredFactors                  : QUILocale.get(lg, lg_prefix + 'requiredFactors'),
                    authPluginsRequiredFactorsWarning: QUILocale.get(lg, lg_prefix + 'authPluginsRequiredFactorsWarning'),
                    allowPasswordLinks               : QUILocale.get(lg, lg_prefix + 'allowPasswordLinks')
                })
            });

            var AuthPluginElm = this.$Elm.getElement('.pcsg-gpm-securityclasses-authplugins');

            Authentication.getAuthPlugins().then(function (authPlugins) {
                for (var i = 0, len = authPlugins.length; i < len; i++) {
                    var Plugin = authPlugins[i];

                    var Label = new Element('label', {
                        'class': 'pcsg-gpm-securityclasses-authplugins-create-label',
                        html   : '<div class="pcsg-gpm-securityclasses-authplugin">' +
                        '<span class="pcsg-gpm-securityclasses-authplugins-create-title">' +
                        Plugin.title +
                        '</span>' +
                        '<span class="pcsg-gpm-securityclasses-authplugins-create-description">' +
                        Plugin.description +
                        '</span>' +
                        '</div>'
                    }).inject(AuthPluginElm);

                    new Element('input', {
                        type  : 'checkbox',
                        name  : 'authplugin',
                        value : Plugin.id,
                        events: {
                            change: self.$refreshFactorSelect
                        }
                    }).inject(Label, 'top');
                }

                self.fireEvent('loaded');
            });

            this.$RequiredFactorsSelect = new QUISelect().inject(
                this.$Elm.getElement('.pcsg-gpm-securityclasses-requiredfactors')
            );

            this.$RequiredFactorsSelect.disable();

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            // @todo
        },

        /**
         * Refreshes selectable number of required authentication factors
         */
        $refreshFactorSelect: function () {
            var authPluginElms = this.$Elm.getElements(
                '.pcsg-gpm-securityclasses-authplugins input'
            );

            var i, len;
            var factors = 0;

            for (i = 0, len = authPluginElms.length; i < len; i++) {
                if (authPluginElms[i].checked) {
                    factors++;
                }
            }

            this.$RequiredFactorsSelect.clear();

            if (factors === 0) {
                this.$RequiredFactorsSelect.disable();
                return;
            }

            this.$RequiredFactorsSelect.enable();

            for (i = 1, len = factors; i <= len; i++) {
                this.$RequiredFactorsSelect.appendChild(
                    i,
                    i,
                    'fa fa-user-secret'
                )
            }

            this.$RequiredFactorsSelect.setValue(factors);
        },

        /**
         * Create the field
         *
         * @returns {Promise}
         */
        submit: function () {
            var self = this;

            var authPluginElms = this.$Elm.getElements(
                '.pcsg-gpm-securityclasses-authplugins input'
            );

            var authPluginIds = [];

            for (var i = 0, len = authPluginElms.length; i < len; i++) {
                if (authPluginElms[i].checked) {
                    authPluginIds.push(authPluginElms[i].value);
                }
            }

            this.$SecurityClassData = QUIFormUtils.getFormData(
                this.$Elm.getElement('form')
            );

            this.$SecurityClassData.authPluginIds   = authPluginIds;
            this.$SecurityClassData.requiredFactors = this.$RequiredFactorsSelect.getValue();

            Authentication.createSecurityClass(
                this.$SecurityClassData
            ).then(function () {
                self.fireEvent('success', [self]);
            });
        }
    });
});
