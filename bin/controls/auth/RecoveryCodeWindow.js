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

    'Ajax',

    'text!package/pcsg/grouppasswordmanager/bin/controls/auth/RecoveryCodeWindow.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/RecoveryCodeWindow.css'

], function (QUI, QUIPopup, QUIButton, QUIFormUtils, QUILocale, Mustache,
             Ajax, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

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
            'RecoveryCodeData': false    // recovery code data
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                backgroundClosable: false,
                closeButton       : false,
                titleCloseButton  : false
            });

            this.addEvents({
                onCreate: this.$onCreate,
                onOpen  : this.$onOpen,
                onClose : this.$onClose
            });

            this.$RecoveryData = null;

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

            var lg_prefix        = 'auth.recoverycodewindow.';
            var RecoveryCodeData = this.getAttribute('RecoveryCodeData');

            self.$RecoveryData = RecoveryCodeData;

            var recoveryCode         = RecoveryCodeData.recoveryCode;
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
                    title              : QUILocale.get(lg, lg_prefix + 'title'),
                    info               : QUILocale.get(lg, lg_prefix + 'info'),
                    basicData          : QUILocale.get(lg, lg_prefix + 'basicData'),
                    username           : QUILocale.get(lg, lg_prefix + 'username'),
                    usernameValue      : RecoveryCodeData.userName + ' (ID: ' + RecoveryCodeData.userId + ')',
                    authPlugin         : QUILocale.get(lg, lg_prefix + 'authplugin'),
                    authPluginValue    : RecoveryCodeData.authPluginTitle + ' (ID: ' + RecoveryCodeData.authPluginId + ')',
                    date               : QUILocale.get(lg, lg_prefix + 'date'),
                    dateValue          : RecoveryCodeData.date,
                    recoveryCode       : recoveryCodeReadable,
                    recoveryCodeId     : QUILocale.get(lg, lg_prefix + 'recoveryCodeId'),
                    recoveryCodeIdValue: RecoveryCodeData.recoveryCodeId
                })
            );
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            var i    = 10;
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
         * event: on popup close
         */
        $onClose: function () {
            this.$RecoveryData = null;
        },

        /**
         * Open new tab with print view and start print automatically
         */
        $print: function () {
            var url = window.location.protocol + '//' +
                window.location.host + URL_OPT_DIR + 'pcsg/grouppasswordmanager/bin/recoverycode.php?';

            url += 'code=' + this.$RecoveryData.recoveryCode;
            url += '&id=' + this.$RecoveryData.recoveryCodeId;
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
