/**
 * Control for editing a security class
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Edit
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
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Edit.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Edit.css
 *
 * @event onLoaded
 * @event onSuccess
 */
define('package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Edit', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/utils/Form',

    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/Actors',
    'package/pcsg/grouppasswordmanager/bin/Authentication',

    'Ajax',

    'text!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Edit.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Edit.css'

], function (QUI, QUIControl, QUIFormUtils, QUILocale, Mustache, Actors,
             Authentication, QUIAjax, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Edit',

        Binds: [
            '$onInject',
            '$searchGroupsToAdd',
            '$insertGroups'
        ],

        options: {
            securityClassId: false // id of security class
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.$existingAuthPlugins = [];
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            var self      = this,
                lg_prefix = 'securityclasses.edit.template.';

            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-securityclasses-create',
                html   : Mustache.render(template, {
                    edittitle         : QUILocale.get(lg, lg_prefix + 'edittitle'),
                    title             : QUILocale.get(lg, lg_prefix + 'title'),
                    description       : QUILocale.get(lg, lg_prefix + 'description'),
                    authPlugins       : QUILocale.get(lg, lg_prefix + 'authPlugins'),
                    authPluginsAdd    : QUILocale.get(lg, lg_prefix + 'authPluginsAdd'),
                    groups            : QUILocale.get(lg, lg_prefix + 'groups'),
                    requiredFactors   : QUILocale.get(lg, lg_prefix + 'requiredFactors'),
                    allowPasswordLinks: QUILocale.get(lg, lg_prefix + 'allowPasswordLinks')
                })
            });

            var AuthPluginElm = this.$Elm.getElement(
                '.pcsg-gpm-securityclasses-authplugins'
            );

            Authentication.getSecurityClassInfo(
                this.getAttribute('securityClassId')
            ).then(function (Info) {
                QUIFormUtils.setDataToForm(Info, self.$Elm.getElement('form'));

                for (var i = 0, len = Info.authPlugins.length; i < len; i++) {
                    var Plugin = Info.authPlugins[i];

                    new Element('label', {
                        'class': 'pcsg-gpm-securityclasses-label',
                        html   : '<div class="pcsg-gpm-securityclasses-authplugin">' +
                        '<span class="pcsg-gpm-securityclasses-authplugin-title">' +
                        Plugin.title +
                        '</span>' +
                        '<span class="pcsg-gpm-securityclasses-authplugin-description">' +
                        Plugin.description +
                        '</span>' +
                        '</div>'
                    }).inject(AuthPluginElm);

                    self.$existingAuthPlugins.push(Plugin.id);
                }

                self.$insertGroups(Info.groups);

                self.$Elm.getElement('.pcsg-gpm-securityclasses-requiredfactors').set(
                    'html',
                    Info.requiredFactors
                );

                self.$insertAuthPlugins();
            });

            return this.$Elm;
        },

        /**
         * Insert authentication plugins that can be added to this security class
         */
        $insertAuthPlugins: function () {
            var self              = this;
            var AuthPluginsAddElm = this.$Elm.getElement('.pcsg-gpm-securityclasses-authplugins-add');

            Authentication.getAuthPlugins().then(function (authPlugins) {
                var available = 0;

                for (var i = 0, len = authPlugins.length; i < len; i++) {
                    var Plugin = authPlugins[i];

                    if (self.$existingAuthPlugins.contains(Plugin.id)) {
                        continue;
                    }

                    var Label = new Element('label', {
                        'class': 'pcsg-gpm-securityclasses-label',
                        html   : '<div class="pcsg-gpm-securityclasses-authplugin">' +
                        '<span class="pcsg-gpm-securityclasses-authplugin-title">' +
                        Plugin.title +
                        '</span>' +
                        '<span class="pcsg-gpm-securityclasses-authplugin-description">' +
                        Plugin.description +
                        '</span>' +
                        '</div>'
                    }).inject(AuthPluginsAddElm);

                    new Element('input', {
                        type : 'checkbox',
                        name : 'authplugin',
                        value: Plugin.id
                    }).inject(Label, 'top');

                    available++;
                }

                if (!available) {
                    new Element('div', {
                        'class': 'pcsg-gpm-password-warning',
                        html   : QUILocale.get(lg, 'securityclasses.edit.addauthfactors.none.available')
                    }).inject(AuthPluginsAddElm);
                } else {
                    new Element('div', {
                        'class': 'pcsg-gpm-password-warning',
                        html   : QUILocale.get(lg, 'securityclasses.edit.addauthfactors.not.removable')
                    }).inject(AuthPluginsAddElm, 'top');
                }

                self.fireEvent('loaded');
            });
        },

        /**
         * Insert groups that are associated with his security class
         *
         * @param groupIds
         */
        $insertGroups: function (groupIds) {
            var GroupsElm = this.$Elm.getElement(
                '.pcsg-gpm-securityclasses-groups'
            );

            for (var i = 0, len = groupIds.length; i < len; i++) {
                Actors.getActor(groupIds[i], 'group').then(function (Actor) {
                    new Element('div', {
                        'class': 'pcsg-gpm-securityclass-edit-group',
                        html   : Actor.name + ' (' + Actor.id + ')'
                    }).inject(GroupsElm);
                });
            }
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            // @todo
        },

        /**
         * Edit the field
         *
         * @returns {Promise}
         */
        submit: function () {
            var self = this;

            var newAuthPluginIds = [];

            var authPluginElms = this.$Elm.getElements(
                '.pcsg-gpm-securityclasses-authplugins-add input'
            );

            for (var i = 0, len = authPluginElms.length; i < len; i++) {
                if (authPluginElms[i].checked) {
                    newAuthPluginIds.push(authPluginElms[i].value);
                }
            }

            this.$SecurityClassData = QUIFormUtils.getFormData(
                this.$Elm.getElement('form')
            );

            this.$SecurityClassData.newAuthPluginIds = newAuthPluginIds;

            return Authentication.editSecurityClass(
                this.getAttribute('securityClassId'),
                this.$SecurityClassData
            ).then(function () {
                self.fireEvent('success', [self]);
            });
        },

        /**
         * Search groups that can be added to the security class
         *
         * @returns {Promise}
         */
        $searchGroupsToAdd: function (value) {
            var self = this;

            return new Promise(function (resolve) {
                QUIAjax.get('package_pcsg_grouppasswordmanager_ajax_auth_securityClassGroupSearch', resolve, {
                    'package'      : 'pcsg/grouppasswordmanager',
                    search         : value,
                    securityClassId: self.getAttribute('securityClassId'),
                    limit          : 10
                });
            });
        }
    });
});
