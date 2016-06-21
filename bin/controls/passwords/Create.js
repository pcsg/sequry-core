/**
 * Control for creating a new password
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/passwords/Create
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/controls/auth/SecurityClassSelect
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/passwords/Create.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/passwords/Create.css
 *
 * @event onLoaded
 */
define('package/pcsg/grouppasswordmanager/bin/controls/passwords/Create', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/utils/Form',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/SecurityClassSelect',


    'text!package/pcsg/grouppasswordmanager/bin/controls/passwords/Create.html'
    //'css!package/pcsg/grouppasswordmanager/bin/controls/passwords/Create.css'

], function (QUI, QUIControl, QUIFormUtils, QUILocale, Mustache, PasswordHandler,
             SecurityClassSelect, template) {
    "use strict";

    var lg        = 'pcsg/grouppasswordmanager',
        Passwords = new PasswordHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/passwords/Create',

        Binds: [
            '$onInject'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Categories = null;

            this.addEvents({
                onInject: this.$onInject
            });

            this.$PasswordData = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            var self      = this,
                lg_prefix = 'create.template.';

            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-password-create',
                html   : Mustache.render(template, {
                    title              : QUILocale.get(lg, lg_prefix + 'title'),
                    basicData          : QUILocale.get(lg, lg_prefix + 'basicData'),
                    securityClass      : QUILocale.get(lg, lg_prefix + 'securityClass'),
                    passwordTitle      : QUILocale.get(lg, lg_prefix + 'passwordTitle'),
                    passwordDescription: QUILocale.get(lg, lg_prefix + 'passwordDescription'),
                    payload            : QUILocale.get(lg, lg_prefix + 'payload'),
                    passwordPayload    : QUILocale.get(lg, lg_prefix + 'passwordPayload'),
                    payloadWarning     : QUILocale.get(lg, lg_prefix + 'payloadWarning'),
                    owner              : QUILocale.get(lg, lg_prefix + 'owner'),
                    passwordOwner      : QUILocale.get(lg, lg_prefix + 'passwordOwner')
                })
            });

            // insert security class select
            var SecurityClassElm = this.$Elm.getElement(
                'span.pcsg-gpm-security-classes'
            );

            this.$SecurityClassSelect = new SecurityClassSelect().inject(
                SecurityClassElm
            );

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            // @todo
        },

        /**
         * Create the field
         *
         * @returns {Promise}
         */
        submit: function () {
            var PasswordData = {
                securityClassId: this.$SecurityClassSelect.getValue(),
                title          : this.$Elm.getElement('input.pcsg-gpm-password-title').value,
                description    : this.$Elm.getElement('input.pcsg-gpm-password-description').value,
                payload        : this.$Elm.getElement('input.pcsg-gpm-password-payload').value
            };

            console.log(PasswordData);
            this.$PasswordData = PasswordData;
        }
    });
});
