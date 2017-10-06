/**
 * Control for creating / viewing password links
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/link/Create
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onSubmit [this] - fires after a new PasswordLink has been successfully created
 * @event onLoaded [this] - fires after everything loaded
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/link/Create', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'controls/email/Select',

    'package/pcsg/grouppasswordmanager/bin/controls/utils/InputButtons',

    'Ajax',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/Passwords',

    'text!package/pcsg/grouppasswordmanager/bin/controls/password/link/Create.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/password/link/Create.css'

], function (QUI, QUIControl, QUIButton, QUIMailSelect, InputButtons, QUIAjax,
             QUILocale, Mustache, Passwords, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/link/Create',

        Binds: [
            'create',
            'submit',
            '$checkPasswordLength'
        ],

        options: {
            passwordId   : false,   // passwordId
            showSubmitBtn: true     // show submit button in control
        },

        initialize: function (options) {
            this.parent(options);

            this.$PasswordInput = null;
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
                    vhostLabel           : QUILocale.get(lg, lgPrefix + 'vhostLabel'),
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

            ValidDateDateSelect.addEvent('change', function (event) {
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

            this.$PasswordInput = this.$Elm.getElement(
                'input[name="password"]'
            );

            var GeneratePinBtn = new QUIButton({
                icon  : 'fa fa-random',
                events: {
                    onClick: function (Btn, event) {
                        event.stop();

                        var Elm = Btn.getAttribute('InputElm');

                        Btn.setAttribute('icon', 'fa fa-spin fa-spinner');
                        Btn.disable();

                        self.$generatePin().then(function (pin) {
                            Elm.value = pin;
                            Btn.setAttribute('icon', 'fa fa-random');
                            Btn.enable();
                        });
                    }
                }
            });

            GeneratePinBtn.disable();

            ActivePasswords.addEvent('change', function () {
                self.$PasswordInput.disabled = !self.$PasswordInput.disabled;

                if (!self.$PasswordInput.disabled) {
                    GeneratePinBtn.enable();
                    self.$PasswordInput.focus();
                } else {
                    self.$PasswordInput.value = '';
                    GeneratePinBtn.disable();
                }
            });

            this.$PasswordInput.addEvents({
                blur: function() {
                    (function() {self.$checkPasswordLength();}.delay(200));
                }
            });

            // add random password btn
            var InputButtonsParser = new InputButtons({
                copy  : false,
                custom: [GeneratePinBtn]
            });

            InputButtonsParser.parse(self.$PasswordInput);

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
                    onChange: function (Control) {
                        EmailsInput.value = Control.getValue();
                    }
                }
            }).imports(this.$Elm.getElement('.pcsg-gpm-password-linkcreate-emails'));

            // vhosts
            var VHostRowElm = this.$Elm.getElement(
                '.pcsg-gpm-password-linkcreate-vhost'
            );

            this.$getVHostList().then(function (vhosts) {
                if (!vhosts.length) {
                    self.$Elm.set(
                        'html',
                        '<div class="pcsg-gpm-password-linkcreate-info">' +
                        QUILocale.get(lg, 'controls.password.linkcreate.no_password_sites') +
                        '</div>'
                    );

                    self.fireEvent('loaded', [self]);
                    return;
                }

                if (vhosts.length <= 1) {
                    VHostRowElm.destroy();
                    self.fireEvent('loaded', [self]);
                    return;
                }

                var VHostSelectElm = self.$Elm.getElement(
                    '.pcsg-gpm-password-linkcreate-vhost-select'
                );

                VHostRowElm.removeClass('pcsg-gpm-password-linkcreate__hidden');

                for (var i = 0, len = vhosts.length; i < len; i++) {
                    new Element('option', {
                        value: vhosts[i],
                        html : vhosts[i]
                    }).inject(VHostSelectElm);
                }

                self.fireEvent('loaded', [self]);
            });

            if (!this.getAttribute('showSubmitBtn')) {
                return this.$Elm;
            }

            this.$Elm.getElement(
                '.pcsg-gpm-password-linkcreate-create'
            ).removeClass('pcsg-gpm-password-linkcreate__hidden');

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
         * Check if password has minimum number of characters (if active)
         *
         * @return {boolean}
         */
        $checkPasswordLength: function () {
            var self = this;

            if (this.$PasswordInput.disabled) {
                return true;
            }

            if (this.$PasswordInput.value.length >= 6) {
                return true;
            }

            QUI.getMessageHandler().then(function (MH) {
                MH.addAttention(
                    QUILocale.get(lg, 'controls.password.linkcreate.password_min_length'),
                    self.$PasswordInput
                );
            });

            this.$PasswordInput.focus();

            return false;
        },

        /**
         * Create new password link
         *
         * @returns {Promise}
         */
        submit: function () {
            if (!this.$checkPasswordLength()) {
                return Promise.reject();
            }

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
        },

        /**
         * Generate a random PIN
         *
         * @return {Promise}
         */
        $generatePin: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get(
                    'package_pcsg_grouppasswordmanager_ajax_passwords_link_generatePin', resolve, {
                        'package': 'pcsg/grouppasswordmanager',
                        onError  : reject
                    }
                );
            });
        },

        /**
         * Get list of virtual hosts
         *
         * @returns {Promise}
         */
        $getVHostList: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get(
                    'package_pcsg_grouppasswordmanager_ajax_passwords_link_getVHostList', resolve, {
                        'package': 'pcsg/grouppasswordmanager',
                        onError  : reject
                    }
                );
            });
        }
    });
});
