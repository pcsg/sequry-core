/**
 * Control for creating / viewing password links
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/Link
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @require Locale
 * @require package/pcsg/grouppasswordmanager/bin/Authentication
 * @require package/pcsg/grouppasswordmanager/bin/Passwords
 * @require package/pcsg/grouppasswordmanager/bin/controls/actors/Select
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/password/Link.css
 *
 * @event onLoaded [this]
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/Link', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/Passwords',

    'text!package/pcsg/grouppasswordmanager/bin/controls/password/Link.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/password/Link.css'

], function (QUI, QUIControl, QUIButton, QUILocale, Mustache, Passwords, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/Link',

        Binds: [
            'create',
            '$onInject'
        ],

        options: {
            passwordId: false // passwordId
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject : this.$onInject,
                onDestroy: this.$onDestroy
            });

            this.$Grid = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            var lgPrefix = 'controls.password.link.template.';

            this.$Elm.set({
                'class': 'pcsg-gpm-password-link',
                html   : Mustache.render(template, {
                    tableHeader   : QUILocale.get(lg, lgPrefix + 'tableHeader'),
                    validDateLabel: QUILocale.get(lg, lgPrefix + 'validDateLabel'),
                    maxCallsLabel : QUILocale.get(lg, lgPrefix + 'maxCallsLabel'),
                    passwordLabel : QUILocale.get(lg, lgPrefix + 'passwordLabel'),
                    messageLabel  : QUILocale.get(lg, lgPrefix + 'messageLabel'),
                    emailLabel    : QUILocale.get(lg, lgPrefix + 'emailLabel'),
                    activeLabel   : QUILocale.get(lg, lgPrefix + 'activeLabel')
                })
            });

            // activate / deactivate event
            var ActiveValidDate = this.$Elm.getElement(
                '.pcsg-gpm-password-link-validDate'
            );

            var ValidDateInput = this.$Elm.getElement(
                'input[name="validDate"]'
            );

            ActiveValidDate.addEvent('change', function () {
                ValidDateInput.disabled = !ValidDateInput.disabled;
            });

            var ActiveMaxCalls = this.$Elm.getElement(
                '.pcsg-gpm-password-link-maxCalls'
            );

            var MaxCallsInput = this.$Elm.getElement(
                'input[name="maxCalls"]'
            );

            ActiveMaxCalls.addEvent('change', function () {
                MaxCallsInput.disabled = !MaxCallsInput.disabled;

                if (!MaxCallsInput.disabled) {
                    MaxCallsInput.focus();
                }
            });

            var ActivePasswords = this.$Elm.getElement(
                '.pcsg-gpm-password-link-password'
            );

            var PasswordInput = this.$Elm.getElement(
                'input[name="password"]'
            );

            ActivePasswords.addEvent('change', function () {
                PasswordInput.disabled = !PasswordInput.disabled;

                if (!PasswordInput.disabled) {
                    PasswordInput.focus();
                }
            });

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            var self = this;

            // build grid and refresh
            self.fireEvent('loaded', [self]);
        },

        /**
         * Create new password link
         *
         * @returns {Promise}
         */
        $submit: function () {

        }
    });
});
