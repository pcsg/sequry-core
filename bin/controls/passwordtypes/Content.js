/**
 * Control for password content input with different types
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Content
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
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Content.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Content.css
 *
 * @event onLoaded
 */
define('package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Content', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale',

    'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Select',

    'css!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Content.css'

], function (QUI, QUIControl, QUILocale, TypeSelect) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Content',

        Binds: [
            '$loadContent'
        ],

        options: {
            type    : false, // initial type
            editable: false  // editable or show only
        },

        initialize: function (options) {
            this.parent(options);

            this.$TypeSelect     = null;
            this.$ContentControl = null;
            this.$ContentElm     = null;
            this.$passwordType   = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-password-typecontent',
                html   : '<div class="pcsg-gpm-password-typecontent-select"></div>' +
                '<div class="pcsg-gpm-password-typecontent-content"></div>'
            });

            this.$ContentElm = this.$Elm.getElement('.pcsg-gpm-password-typecontent-content');

            var TypeSelectElm = this.$Elm.getElement('.pcsg-gpm-password-typecontent-select');

            this.$TypeSelect = new TypeSelect({
                initialValue: this.getAttribute('type'),
                events      : {
                    onChange: this.$loadContent
                }
            }).inject(TypeSelectElm);

            if (!this.getAttribute('editable')) {
                TypeSelectElm.setStyle('display', 'none');
                this.$TypeSelect.disable();
            }

            return this.$Elm;
        },

        /**
         * load password type content control
         *
         * @param {string} type
         */
        $loadContent: function (type) {
            var self = this;

            this.$ContentElm.set('html', '');

            require([
                'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/' + type
            ], function (ContentControl) {
                self.$ContentControl = new ContentControl().inject(self.$ContentElm);
                self.$passwordType   = type;
                self.fireEvent('loaded');
            });
        },

        /**
         * Set content to current control
         *
         * @param {Object} Content
         */
        setData: function (Content) {
            if (!this.$ContentControl) {
                return;
            }

            this.$ContentControl.setData(Content);
        },

        /**
         * Get content from current control
         *
         * @return {Object}
         */
        getData: function () {
            if (!this.$ContentControl) {
                return;
            }

            var Data = this.$ContentControl.getData();

            Data.type = this.$passwordType;

            return Data;
        }
    });
});