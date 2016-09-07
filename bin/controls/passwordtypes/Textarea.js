/**
 * Simple Textarea Textarea type for passwords
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Textarea
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
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Textarea.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Textarea.css
 *
 * @event onLoaded
 * @event onFinish
 */
define('package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Textarea', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/utils/Form',

    'qui/controls/buttons/Button',

    'Locale',
    'Mustache',

    'text!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Textarea.html'
    //'css!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Textarea.css'

], function (QUI, QUIControl, QUIFormUtils, QUIButton, QUILocale, Mustache, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Textarea',

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
            var lg_prefix = 'password.types.Textarea.template.';

            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-password-types-Textarea',
                html   : Mustache.render(template, {
                    title   : QUILocale.get(lg, lg_prefix + 'title'),
                    password: QUILocale.get(lg, lg_prefix + 'password'),
                    note    : QUILocale.get(lg, lg_prefix + 'note')
                })
            });

            var PasswordElm = this.$Elm.getElement('textarea[name="gpm_password"]');

            PasswordElm.setStyle('float', 'left');

            new QUIButton({
                icon  : 'fa fa-clipboard',
                styles: {
                    marginLeft: 5
                },
                events: {
                    onClick: function () {
                        PasswordElm.focus();
                        PasswordElm.select();
                    }
                }
            }).inject(PasswordElm.getParent(), 'bottom');

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
