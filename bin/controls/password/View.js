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
    'Locale',

    'package/pcsg/grouppasswordmanager/bin/controls/password/Authenticate',
    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Content',

    'css!package/pcsg/grouppasswordmanager/bin/controls/password/View.css'

], function (QUI, QUIControl, QUILocale, AuthenticationControl, PasswordHandler,
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
                events         : {
                    onSubmit: function (AuthData) {
                        Passwords.getView(
                            self.getAttribute('passwordId'),
                            AuthData
                        ).then(
                            function (PasswordData) {
                                AuthControl.destroy();

                                self.$Elm.set(
                                    'html',
                                    '<h1 class="pcsg-gpm-password-view-info-title">' +
                                    PasswordData.title +
                                    '</h1>' +
                                    '<div class="pcsg-gpm-password-view-info">' +
                                    '<p class="pcsg-gpm-password-view-info-datatype">' +
                                    PasswordData.dataType +
                                    '</p>' +
                                    '<p class="pcsg-gpm-password-view-info-description">' +
                                    PasswordData.description +
                                    '</p>' +
                                    '</div>' +
                                    '<div class="pcsg-gpm-password-view-payload"></div>'
                                );

                                var PassContent = new PasswordContent({
                                    type  : PasswordData.dataType,
                                    events: {
                                        onLoaded: function () {
                                            PassContent.setData(PasswordData.payload);
                                            self.fireEvent('loaded');
                                        }
                                    }
                                }).inject(
                                    self.$Elm.getElement('.pcsg-gpm-password-view-payload')
                                );
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
        }
    });
});
