/**
 * Popup that opens and displays the registration control for a specific authentication plugin
 *
 * @module package/sequry/core/bin/controls/auth/RegisterPopup
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onSuccess [this] - Fires on successful authentication
 */
define('package/sequry/core/bin/controls/auth/RegisterPopup', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'Locale',
    'Mustache',

    'package/sequry/core/bin/Authentication',
    'package/sequry/core/bin/controls/auth/Register',
    'package/sequry/core/bin/controls/auth/recovery/CodePopup',

    'text!package/sequry/core/bin/controls/auth/RegisterPopup.html',
    'css!package/sequry/core/bin/controls/auth/RegisterPopup.css'

], function (QUI, QUIPopup, QUIButton, QUILocale, Mustache, Authentication, RegisterControl,
             RecoveryCodePopup, template) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/sequry/core/bin/controls/auth/RegisterPopup',

        Binds: [
            '$onOpen'
        ],

        options: {
            authPluginId      : false, // ID of the authentication plugin to register for,
            maxHeight         : 400,
            maxWidth          : 600,
            icon              : 'fa fa-user',
            'class'           : 'sequry-auth-registerpopup',
            backgroundClosable: true,

            // buttons
            buttons         : true,
            closeButton     : true,
            titleCloseButton: true
        },

        initialize: function (options) {
            options.title           = QUILocale.get(lg, 'controls.auth.RegisterPopup.title');
            options.closeButtonText = QUILocale.get(lg, 'controls.auth.RegisterPopup.close_btn');

            this.parent(options);

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * Event: onOpen (if Popup opens)
         */
        $onOpen: function () {
            var self     = this;
            var Content  = this.getContent();
            var lgPrefix = 'controls.auth.RegisterPopup.template.';

            this.setContent(Mustache.render(template, {
                info: QUILocale.get(lg, lgPrefix + 'info')
            }));

            var Registration = new RegisterControl({
                authPluginId: this.getAttribute('authPluginId')
            }).inject(Content);

            this.addButton(new QUIButton({
                text     : QUILocale.get(lg, 'controls.auth.RegisterPopup.btn.register'),
                textimage: 'fa fa-check',
                events   : {
                    onClick: function () {
                        self.Loader.show();
                        Registration.submit().then(function (RecoveryCodeData) {
                            self.Loader.hide();

                            if (!RecoveryCodeData) {
                                return;
                            }

                            new RecoveryCodePopup({
                                RecoveryCodeData: RecoveryCodeData,
                                events          : {
                                    onClose: function () {
                                        self.fireEvent('success', [self]);
                                        self.close();
                                    }
                                }
                            }).open();
                        });
                    }
                }
            }));
        }
    });
});
