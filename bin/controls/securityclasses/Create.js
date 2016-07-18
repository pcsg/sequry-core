/**
 * Control for creating a new password
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Create
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Mustache
 * @require Locale
 * @require package/pcsg/grouppasswordmanager/bin/classes/Passwords
 * @require package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
 * @require package/pcsg/grouppasswordmanager/bin/controls/actors/EligibleActorSelect
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Create.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Create.css
 *
 * @event onLoaded
 * @event onSuccess
 */
define('package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Create', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Select',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',

    'text!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Create.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Create.css'

], function (QUI, QUIControl, QUISelect, QUILocale, Mustache, AuthenticationHandler, template) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager',
        Authentication = new AuthenticationHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Create',

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
                'class': 'pcsg-gpm-password-create',
                html   : Mustache.render(template, {
                    createtitle       : QUILocale.get(lg, lg_prefix + 'createtitle'),
                    title             : QUILocale.get(lg, lg_prefix + 'title'),
                    description       : QUILocale.get(lg, lg_prefix + 'description'),
                    authPlugins       : QUILocale.get(lg, lg_prefix + 'authPlugins'),
                    authPluginsWarning: QUILocale.get(lg, lg_prefix + 'authPluginsWarning'),
                    groups            : QUILocale.get(lg, lg_prefix + 'groups'),
                    groupsInfo        : QUILocale.get(lg, lg_prefix + 'groupsInfo'),
                    requiredFactors   : QUILocale.get(lg, lg_prefix + 'requiredFactors')
                })
            });

            var AuthPluginElm = this.$Elm.getElement('.pcsg-gpm-password-authplugins');

            Authentication.getAuthPlugins().then(function (authPlugins) {
                for (var i = 0, len = authPlugins.length; i < len; i++) {
                    var Plugin = authPlugins[i];

                    var Label = new Element('label', {
                        'class': 'pcsg-gpm-password-authplugins-label',
                        html   : '<div class="pcsg-gpm-password-authplugin">' +
                        '<span class="pcsg-gpm-password-authplugins-title">' +
                        Plugin.title +
                        '</span>' +
                        '<span class="pcsg-gpm-password-authplugins-description">' +
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
                this.$Elm.getElement('.pcsg-gpm-password-requiredfactors')
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
                '.pcsg-gpm-password-authplugins input'
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
                '.pcsg-gpm-password-authplugins input'
            );

            var authPluginIds = [];

            for (var i = 0, len = authPluginElms.length; i < len; i++) {
                if (authPluginElms[i].checked) {
                    authPluginIds.push(authPluginElms[i].value);
                }
            }

            this.$SecurityClassData = {
                title          : this.$Elm.getElement('.pcsg-gpm-securityclasses-title').value,
                description    : this.$Elm.getElement('.pcsg-gpm-securityclasses-description').value,
                authPluginIds  : authPluginIds,
                requiredFactors: this.$RequiredFactorsSelect.getValue()
            };

            Authentication.createSecurityClass(
                this.$SecurityClassData
            ).then(function () {
                self.fireEvent('success', [self]);
            });
        }
    });
});
