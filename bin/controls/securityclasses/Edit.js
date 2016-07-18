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
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',
    'package/pcsg/grouppasswordmanager/bin/controls/actors/Select',

    'Ajax',

    'text!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Edit.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Edit.css'

], function (QUI, QUIControl, QUILocale, Mustache, AuthenticationHandler, ActorSelect, QUIAjax, template) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager',
        Authentication = new AuthenticationHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Edit',

        Binds: [
            '$onInject',
            '$searchGroupsToAdd'
        ],

        options: {
            securityClassId: false // id of security class
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });
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
                'class': 'pcsg-gpm-password-create',
                html   : Mustache.render(template, {
                    edittitle      : QUILocale.get(lg, lg_prefix + 'edittitle'),
                    title          : QUILocale.get(lg, lg_prefix + 'title'),
                    description    : QUILocale.get(lg, lg_prefix + 'description'),
                    authPlugins    : QUILocale.get(lg, lg_prefix + 'authPlugins'),
                    groups         : QUILocale.get(lg, lg_prefix + 'groups'),
                    requiredFactors: QUILocale.get(lg, lg_prefix + 'requiredFactors')
                })
            });

            this.$GroupSelect = new ActorSelect({
                actorType: 'groups',
                Search   : this.$searchGroupsToAdd
            }).inject(
                this.$Elm.getElement('.pcsg-gpm-securityclasses-groups')
            );

            var AuthPluginElm = this.$Elm.getElement(
                '.pcsg-gpm-password-authplugins'
            );

            Authentication.getSecurityClassInfo(
                this.getAttribute('securityClassId')
            ).then(function (Info) {
                self.$Elm.getElement('.pcsg-gpm-securityclasses-title').value       = Info.title;
                self.$Elm.getElement('.pcsg-gpm-securityclasses-description').value = Info.description;

                var i, len;

                for (i = 0, len = Info.authPlugins.length; i < len; i++) {
                    var Plugin = Info.authPlugins[i];

                    new Element('label', {
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
                }

                for (i = 0, len = Info.groups.length; i < len; i++) {
                    self.$GroupSelect.addItem('g' + Info.groups[i]);
                }

                self.$Elm.getElement('.pcsg-gpm-password-requiredfactors').set(
                    'html',
                    Info.requiredFactors
                );

                self.fireEvent('loaded');
            });

            return this.$Elm;
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

            this.$SecurityClassData = {
                title      : this.$Elm.getElement('.pcsg-gpm-securityclasses-title').value,
                description: this.$Elm.getElement('.pcsg-gpm-securityclasses-description').value,
                groups     : this.$GroupSelect.getActors()
            };

            Authentication.editSecurityClass(
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
