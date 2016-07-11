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
 * @event onFinish
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/View', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'Locale',

    'package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate',
    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Content',

    'Ajax',

    'css!package/pcsg/grouppasswordmanager/bin/controls/password/View.css'

], function (QUI, QUIControl, QUIPopup, QUIButton, QUILocale, AuthenticationControl,
             PasswordHandler, PasswordContent, Ajax) {
    "use strict";

    var lg        = 'pcsg/grouppasswordmanager',
        Passwords = new PasswordHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/View',

        Binds: [
            '$onInject',
            'submit'
        ],

        options: {
            'passwordId'     : false,   // id of the password
            'securityClassId': false    // id the security class of the password
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject : this.$onInject,
                onDestroy: this.$onDestroy
            });

            this.$ViewPopup = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            return this.parent();
        },

        /**
         * event : oninject
         */
        $onInject: function () {
            // @todo
        },

        /**
         * event: ondestroy
         */
        $onDestroy: function () {
            if (this.$ViewPopup) {
                this.$ViewPopup.close();
            }
        },

        open: function () {
            var self = this;

            var ViewPopup = new QUIPopup({
                title      : QUILocale.get(
                    lg, 'controls.password.view.popup.title'
                ),
                maxHeight  : 300,
                maxWidth   : 500,
                closeButton: true,
                content    : '<div class="pcsg-gpm-password-view-title"></div>' +
                '<div class="pcsg-gpm-password-view-payload"></div>'
            });

            this.$ViewPopup = ViewPopup;

            ViewPopup.open();

            var Content    = ViewPopup.getContent();
            var TitleElm   = Content.getElement('.pcsg-gpm-password-view-title');
            var PayloadElm = Content.getElement('.pcsg-gpm-password-view-payload');

            var AuthControl = new AuthenticationControl({
                securityClassId: this.getAttribute('securityClassId'),
                events         : {
                    onSubmit: function (AuthData) {
                        Passwords.getView(
                            self.getAttribute('passwordId'),
                            AuthData
                        ).then(
                            function (PasswordData) {
                                AuthControl.destroy();

                                TitleElm.set(
                                    'html',
                                    '<h1 class="pcsg-gpm-password-view-info-title">' +
                                    PasswordData.title +
                                    '</h1>' +
                                    '<span class="pcsg-gpm-password-view-info-description">' +
                                    PasswordData.description +
                                    '</span>'
                                );

                                var PassContent = new PasswordContent({
                                    type: PasswordData.payload.type,
                                    events: {
                                        onLoaded: function() {
                                            PassContent.setData(PasswordData.payload);
                                        }
                                    }
                                }).inject(PayloadElm);
                            },
                            function () {
                                // @todo
                            }
                        );
                    },
                    onClose : function () {
                        ViewPopup.close();
                    }
                }
            });

            AuthControl.open();
        }
    });
});
