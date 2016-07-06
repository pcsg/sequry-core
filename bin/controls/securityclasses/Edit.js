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

    'text!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Edit.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Edit.css'

], function (QUI, QUIControl, QUILocale, Mustache, AuthenticationHandler, template) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager',
        Authentication = new AuthenticationHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Edit',

        Binds: [
            '$onInject'
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
                    edittitle  : QUILocale.get(lg, lg_prefix + 'edittitle'),
                    title      : QUILocale.get(lg, lg_prefix + 'title'),
                    description: QUILocale.get(lg, lg_prefix + 'description')
                })
            });

            Authentication.getSecurityClassInfo(
                this.getAttribute('securityClassId')
            ).then(function (Info) {
                self.$Elm.getElement('.pcsg-gpm-securityclasses-title').value = Info.title;
                self.$Elm.getElement('.pcsg-gpm-securityclasses-description').value = Info.description;

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
                title        : this.$Elm.getElement('.pcsg-gpm-securityclasses-title').value,
                description  : this.$Elm.getElement('.pcsg-gpm-securityclasses-description').value
            };

            Authentication.editSecurityClass(
                this.getAttribute('securityClassId'),
                this.$SecurityClassData
            ).then(function () {
                self.fireEvent('success', [self]);
            });
        }
    });
});
