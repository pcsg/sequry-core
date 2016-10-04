/**
 * Control for password content input with different types
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Edit
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
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Edit.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Edit.css
 *
 * @event onLoaded [this]
 */
define('package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Edit', [

    'qui/QUI',
    'qui/controls/Control',

    'Ajax',
    'Locale',

    'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Select'

    //'css!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Edit.css'

], function (QUI, QUIControl, QUIAjax, QUILocale, TypeSelect) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Edit',

        Binds: [
            '$onInject'
        ],

        options: {
            type   : false, // initial type
            Content: false // password content
        },

        initialize: function (options) {
            this.parent(options);
            this.$TypeSelect = null;

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
                'class': 'pcsg-gpm-password-types-edit',
                html   : '<div class="pcsg-gpm-password-types-edit-select"></div>' +
                '<div class="pcsg-gpm-password-types-edit-content"></div>'
            });

            this.$EditContent = this.$Elm.getElement('.pcsg-gpm-password-types-edit-content');

            //var TypeSelectElm = this.$Elm.getElement('.pcsg-gpm-password-types-edit-select');
            //
            //this.$TypeSelect = new TypeSelect({
            //    initialValue: this.getAttribute('type'),
            //    events      : {
            //        onChange: this.$loadEdit
            //    }
            //}).inject(TypeSelectElm);
            //
            //if (!this.getAttribute('editable')) {
            //    TypeSelectElm.setStyle('display', 'none');
            //    this.$TypeSelect.disable();
            //}

            return this.$Elm;
        },

        /**
         * event: on control inject into DOM
         */
        $onInject: function () {
            this.$loadTemplate();
        },

        /**
         * Load password type edit template
         */
        $loadTemplate: function () {
            var self = this;

            QUIAjax.get(
                'package_pcsg_grouppasswordmanager_ajax_passwordtypes_getEditHtml',
                function (templateHtml) {
                    self.$EditContent.set('html', templateHtml);
                    self.fireEvent('loaded', [self]);
                }, {
                    'package': 'pcsg/grouppasswordmanager',
                    type     : this.getAttribute('type')
                }
            );
        },

        /**
         * Get all passwordtype fields
         */
        $getFields: function () {
            return this.$EditContent.getElements('.pcsg-gpm-password-edit-value');
        },

        /**
         * Set content to current control
         *
         * @param {Object} Data
         */
        setData: function (Data) {
            var fields = this.$getFields();

            for (var i = 0, len = fields.length; i < len; i++) {
                var FieldElm  = fields[i];
                var fieldName = FieldElm.getProperty('name');

                if (fieldName in Data) {
                    FieldElm.value = Data[fieldName];
                }
            }
        },

        /**
         * Get content from current control
         *
         * @return {Object}
         */
        getData: function () {
            var fields = this.$getFields();
            var Data   = {};

            for (var i = 0, len = fields.length; i < len; i++) {
                var FieldElm                       = fields[i];
                Data[FieldElm.getProperty('name')] = FieldElm.value;
            }

            return Data;
        }
    });
});
