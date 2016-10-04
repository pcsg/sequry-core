/**
 * Control for creating a new password
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/View
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/controls/passwords/SecurityClassSelect
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/password/View.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/password/View.css
 *
 * @event onLoaded
 * @event onClose
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/View', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'Locale',

    'package/pcsg/grouppasswordmanager/bin/controls/password/Authenticate',
    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Content',

    'css!package/pcsg/grouppasswordmanager/bin/controls/password/View.css'

], function (QUI, QUIControl, QUIButton, QUILocale, AuthenticationControl, PasswordHandler,
             PasswordContent) {
    "use strict";

    var lg        = 'pcsg/grouppasswordmanager',
        Passwords = new PasswordHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/View',

        Binds: [
            '$onInject'
        ],

        options: {
            'passwordId': false   // id of the password
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
            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-password-create',
                html   : '<p class="pcsg-gpm-password-view-payload">' +
                QUILocale.get(lg, 'password.view.restricted.info') +
                '</p>'
            });

            return this.$Elm;
        },

        /**
         * event : oninject
         *
         * Ask user for authentication information and load password data
         */
        $onInject: function () {
            var self = this;

            var AuthControl = new AuthenticationControl({
                passwordId: this.getAttribute('passwordId'),
                events    : {
                    onSubmit: function (AuthData) {
                        Passwords.getView(
                            self.getAttribute('passwordId'),
                            AuthData
                        ).then(
                            function (viewHtml) {
                                AuthControl.destroy();

                                self.$Elm.set(
                                    'html',
                                    viewHtml
                                );

                                self.$parseView();

                                self.fireEvent('loaded');

                                //var PassContent = new PasswordContent({
                                //    type  : PasswordData.dataType,
                                //    mode  : 'view',
                                //    events: {
                                //        onLoaded: function () {
                                //            self.fireEvent('loaded');
                                //        }
                                //    }
                                //}).inject(
                                //    self.$Elm.getElement('.pcsg-gpm-password-view-payload')
                                //);
                            },
                            function () {
                                // @todo
                            }
                        );
                    },
                    onClose : function () {
                        self.fireEvent('close');
                    }
                }
            });

            AuthControl.open();
        },

        $parseView: function () {
            // copy elements
            var i, len, Elm, ValueInput;
            var copyElms = this.$Elm.getElements('.pwm-passwordtypes-copy');

            for (i = 0, len = copyElms.length; i < len; i++) {
                Elm = copyElms[i];

                ValueInput = new Element('input', {
                    'type'  : 'text',
                    'class' : 'pcsg-gpm-password-view-value',
                    readonly: 'readonly',
                    value   : Elm.innerHTML.trim()
                });

                new QUIButton({
                    Elm   : ValueInput,
                    icon  : 'fa fa-copy',
                    events: {
                        onClick: function (Btn) {
                            var Elm = Btn.getAttribute('Elm');
                            Elm.select();
                        }
                    }
                }).inject(Elm.getParent(), 'after');

                ValueInput.replaces(Elm);
            }

            // copy and hide elements
            var copyHideElms = this.$Elm.getElements('.pwm-passwordtypes-copy-hide');

            for (i = 0, len = copyHideElms.length; i < len; i++) {
                Elm = copyHideElms[i];

                ValueInput = new Element('input', {
                    'type'  : 'password',
                    'class' : 'pcsg-gpm-password-view-value',
                    readonly: 'readonly',
                    events: {
                        blur: function(event) {
                            // @todo input = password
                        }
                    },
                    value   : Elm.innerHTML.trim()
                });

                new QUIButton({
                    Elm   : ValueInput,
                    icon  : 'fa fa-eye',
                    action: 'show',
                    events: {
                        onClick: function (Btn) {
                            var Elm = Btn.getAttribute('Elm');

                            if (Btn.getAttribute('action') === 'show') {
                                Btn.setAttributes({
                                    icon  : 'fa fa-eye-slash',
                                    action: 'hide'
                                });

                                Elm.setProperty('type', 'text');
                                Elm.focus();
                                Elm.select();

                                return;
                            }

                            Btn.setAttributes({
                                icon  : 'fa fa-eye',
                                action: 'show'
                            });

                            Elm.setProperty('type', 'password');
                            Elm.blur();
                        }
                    }
                }).inject(Elm.getParent(), 'after');

                ValueInput.replaces(Elm);
            }
        }
    });
});
