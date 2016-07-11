/**
 * FTP Input type for passwords
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/FTP
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Mustache
 * @require Locale
 * @require package/pcsg/grouppasswordmanager/bin/classes/Passwords
 * @require package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
 * @require package/pcsg/grouppasswordmanager/bin/controls/actors/EligibleActorSelect
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/FTP.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/FTP.css
 *
 * @event onLoaded
 * @event onFinish
 */
define('package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/FTP', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/utils/Form',

    'Locale',
    'Mustache',

    'text!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/FTP.html',
    //'css!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/FTP.css'

], function (QUI, QUIControl, QUIFormUtils, QUILocale, Mustache, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/FTP',

        Binds: [
            'setData',
            'getData'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$PasswordData = null;
            this.$Form         = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            var lg_prefix = 'password.types.ftp.template.';

            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-password-types-ftp',
                html   : Mustache.render(template, {
                    ftpData : QUILocale.get(lg, lg_prefix + 'ftpData'),
                    host    : QUILocale.get(lg, lg_prefix + 'host'),
                    user    : QUILocale.get(lg, lg_prefix + 'user'),
                    password: QUILocale.get(lg, lg_prefix + 'password'),
                    note    : QUILocale.get(lg, lg_prefix + 'note')
                })
            });

            this.$Form = this.$Elm.getElement('form');

            return this.$Elm;
        },

        /**
         * Set form content
         *
         * @param {Object} Content
         */
        setData: function (Content) {
            QUIFormUtils.setDataToForm(Content, this.$Form);
        },

        /**
         * Get form content
         *
         * @return {Object}
         */
        getData: function () {
            return QUIFormUtils.getFormData(this.$Form);
        }
    });
});
