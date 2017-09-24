/**
 * Control for creating / viewing password links
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/link/Create
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/Passwords
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/password/link/Create.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/password/link/Create.css
 *
 * @event onSubmit [this] - fires after a new PasswordLink has been successfully created
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/link/Create', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'controls/email/Select',

    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/Passwords',

    'text!package/pcsg/grouppasswordmanager/bin/controls/password/link/Create.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/password/link/Create.css'

], function (QUI, QUIControl, QUIButton, QUIMailSelect, QUILocale, Mustache, Passwords, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/link/Create',

        Binds: [
            'create'
        ],

        options: {
            passwordId   : false,   // passwordId
            showSubmitBtn: true     // show submit button in control
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            var self     = this;
            var lgPrefix = 'controls.password.linkcreate.template.';

            this.$Elm.set({
                'class': 'pcsg-gpm-password-linkcreate',
                html   : Mustache.render(template, {
                    tableHeader          : QUILocale.get(lg, lgPrefix + 'tableHeader'),
                    validDateLabel       : QUILocale.get(lg, lgPrefix + 'validDateLabel'),
                    maxCallsLabel        : QUILocale.get(lg, lgPrefix + 'maxCallsLabel'),
                    passwordLabel        : QUILocale.get(lg, lgPrefix + 'passwordLabel'),
                    titleLabel           : QUILocale.get(lg, lgPrefix + 'titleLabel'),
                    messageLabel         : QUILocale.get(lg, lgPrefix + 'messageLabel'),
                    emailLabel           : QUILocale.get(lg, lgPrefix + 'emailLabel'),
                    activeLabel          : QUILocale.get(lg, lgPrefix + 'activeLabel'),
                    validDateOption1Day  : QUILocale.get(lg, lgPrefix + 'validDateOption1Day'),
                    validDateOption3Day  : QUILocale.get(lg, lgPrefix + 'validDateOption3Day'),
                    validDateOption1Week : QUILocale.get(lg, lgPrefix + 'validDateOption1Week'),
                    validDateOption1Month: QUILocale.get(lg, lgPrefix + 'validDateOption1Month'),
                    validDateOptionDate  : QUILocale.get(lg, lgPrefix + 'validDateOptionDate')
                })
            });

            // activate / deactivate event
            var ActiveValidDate = this.$Elm.getElement(
                '.pcsg-gpm-password-linkcreate-validDate'
            );

            var ValidDateSelect = this.$Elm.getElement(
                'select[name="validDateSelect"]'
            );

            var ValidDateInput = this.$Elm.getElement(
                'input[name="validDate"]'
            );

            var ValidDateDateSelect = this.$Elm.getElement(
                '.pcsg-gpm-password-linkcreate-date'
            );

            ValidDateDateSelect.addEvent('change', function(event) {
                ValidDateInput.value = event.target.value;
            });

            ValidDateSelect.addEvent('change', function (event) {
                if (event.target.value !== 'date') {
                    ValidDateDateSelect.setStyle('display', 'none');
                    ValidDateInput.value = event.target.value;
                    return;
                }

                ValidDateDateSelect.setStyle('display', 'block');
                ValidDateInput.value = ValidDateDateSelect.value;
            });

            ActiveValidDate.addEvent('change', function () {
                ValidDateSelect.disabled = !ValidDateSelect.disabled;
            });

            var ActiveMaxCalls = this.$Elm.getElement(
                '.pcsg-gpm-password-linkcreate-maxCalls'
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
                '.pcsg-gpm-password-linkcreate-password'
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

            // set default title and description
            Passwords.getLinkPasswordData(this.getAttribute('passwordId')).then(function (Password) {
                self.$Elm.getElement(
                    'input[name="title"]'
                ).value = Password.title;

                self.$Elm.getElement(
                    'textarea[name="message"]'
                ).value = Password.description;
            });

            // emails
            var EmailsInput = this.$Elm.getElement(
                'input[name="email"]'
            );

            new QUIMailSelect({
                events: {
                    onChange: function(Control) {
                        EmailsInput.value = Control.getValue();
                    }
                }
            }).imports(this.$Elm.getElement('.pcsg-gpm-password-linkcreate-emails'));

            if (!this.getAttribute('showSubmitBtn')) {
                return this.$Elm;
            }

            // submit btn
            new QUIButton({
                textimage: 'fa fa-link',
                text     : QUILocale.get(lg, 'controls.password.linkcreate.btn'),
                styles   : {
                    margin: 'auto'
                },
                events   : {
                    onClick: function (Btn) {
                        Btn.disable();

                        self.submit.then(function () {
                            self.fireEvent('submit', [self]);
                        }, function () {
                            Btn.enable();
                        });
                    }
                }
            }).inject(
                this.$Elm.getElement(
                    'div.pcsg-gpm-password-linkcreate-createbtn'
                )
            );

            return this.$Elm;
        },

        /**
         * Create new password link
         *
         * @returns {Promise}
         */
        submit: function () {
            var formElements = this.$Elm.getElements(
                '.pcsg-gpm-password-linkcreate-option'
            );

            var LinkCreateData = {};

            for (var i = 0, len = formElements.length; i < len; i++) {
                var FormElm = formElements[i];

                if (FormElm.disabled) {
                    continue;
                }

                LinkCreateData[FormElm.get('name')] = FormElm.value;
            }

            return Passwords.createLink(
                this.getAttribute('passwordId'),
                LinkCreateData
            );
        }
    });
});
