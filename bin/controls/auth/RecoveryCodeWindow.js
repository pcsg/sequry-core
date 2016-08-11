/**
 * Control for showing / printing recovery code information
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/RecoveryCodeWindow
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/auth/RecoveryCodeWindow.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/auth/RecoveryCodeWindow.css
 *
 * @event onFinish
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/RecoveryCodeWindow', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'qui/utils/Form',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',

    'Ajax',

    'text!package/pcsg/grouppasswordmanager/bin/controls/auth/RecoveryCodeWindow.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/RecoveryCodeWindow.css'

], function (QUI, QUIPopup, QUIButton, QUIFormUtils, QUILocale, Mustache,
             AuthHandler, Ajax, template) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager',
        Authentication = new AuthHandler();

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/auth/RecoveryCodeWindow',

        Binds: [
            '$onInject',
            'submit',
            '$showRecovery',
            '$print'
        ],

        options: {
            'authPluginId'   : false,   // id of auth plugin the recovery code is for
            'authPluginTitle': false, // title of authentication plugin
            'recoveryCode'   : false    // recovery code
        },

        initialize: function (options) {
            var self = this;

            this.parent(options);

            this.setAttributes({
                backgroundClosable: false,
                closeButton       : false,
                titleCloseButton  : false
            });

            this.addEvents({
                onCreate: this.$onCreate,
                onOpen  : this.$onOpen
            });

            this.$CloseBtn = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        $onCreate: function () {
            var self = this;

            this.$CloseBtn = new QUIButton({
                textimage: 'fa fa-close',
                text     : QUILocale.get(lg, 'auth.recoverycodewindow.close.btn.text'),
                alt      : QUILocale.get(lg, 'auth.recoverycodewindow.close.btn'),
                title    : QUILocale.get(lg, 'auth.recoverycodewindow.close.btn'),
                events   : {
                    onClick: function () {
                        self.close();
                    }
                }
            });

            var lg_prefix = 'auth.recoverycodewindow.';

            var recoveryCode         = this.getAttribute('recoveryCode');
            var recoveryCodeReadable = '';

            for (var i = 0, len = recoveryCode.length; i < len; i++) {
                if (i % 5 === 0 && i > 0) {
                    recoveryCodeReadable += '-';
                }

                recoveryCodeReadable += recoveryCode[i];
            }

            this.addButton(this.$CloseBtn);
            this.$CloseBtn.disable();

            this.addButton(new QUIButton({
                textimage: 'fa fa-print',
                text     : QUILocale.get(lg, 'auth.recoverycodewindow.print.btn.text'),
                alt      : QUILocale.get(lg, 'auth.recoverycodewindow.print.btn'),
                title    : QUILocale.get(lg, 'auth.recoverycodewindow.print.btn'),
                events   : {
                    onClick: self.$print
                }
            }));

            this.setContent(
                Mustache.render(template, {
                    title          : QUILocale.get(lg, lg_prefix + 'title'),
                    info           : QUILocale.get(lg, lg_prefix + 'info'),
                    basicData      : QUILocale.get(lg, lg_prefix + 'basicData'),
                    username       : QUILocale.get(lg, lg_prefix + 'username'),
                    usernameValue  : USER.name,
                    authPlugin     : QUILocale.get(lg, lg_prefix + 'authplugin'),
                    authPluginValue: this.getAttribute('authPluginTitle'),
                    date           : QUILocale.get(lg, lg_prefix + 'date'),
                    dateValue      : new Date().format('%d.%m.%Y'),
                    recoveryCode   : recoveryCodeReadable
                })
            );
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            var i    = 1;
            var self = this;

            var CloseCountdown = setInterval(function () {
                if (i === 0) {
                    clearInterval(CloseCountdown);

                    self.$CloseBtn.setAttributes({
                        text : QUILocale.get(lg, 'auth.recoverycodewindow.close.btn.text'),
                        alt  : QUILocale.get(lg, 'auth.recoverycodewindow.close.btn'),
                        title: QUILocale.get(lg, 'auth.recoverycodewindow.close.btn')
                    });

                    self.$CloseBtn.enable();

                    return;
                }

                self.$CloseBtn.setAttribute(
                    'text',
                    QUILocale.get(lg, 'auth.recoverycodewindow.popup.recoverycode.btn.text.countdown', {
                        number: i--
                    })
                );
            }, 1000);
        },

        /**
         * Open new tab with print view and start print automatically
         */
        $print: function () {
            var url = window.location.protocol + '//' +
                window.location.host + URL_OPT_DIR + 'pcsg/grouppasswordmanager/bin/recoverycode.php?';

            url += 'code=' + this.getAttribute('recoveryCode');
            url += '&authPluginId=' + this.getAttribute('authPluginId');
            url += '&lang=' + USER.lang;

            var Link = new Element('a', {
                href  : url,
                target: '_blank'
            }).inject(document.body);

            Link.click();
            Link.destroy();
        }
    });
});
