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

    'qui/controls/Control',
    'qui/controls/buttons/Button',

    'Ajax',
    'Locale',

    'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Select',

    'css!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Edit.css'

], function (QUIControl, QUIButton, QUIAjax, QUILocale, TypeSelect) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Edit',

        Binds: [
            '$onInject',
            '$parseTemplate'
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
                    //self.$EditContent.getElement('table').addClass(
                    //    'pcsg-gpm-password-payload-table'
                    //);
                    self.$parseTemplate();
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
         *
         */
        $parseTemplate: function () {
            // show/hide elements
            var Elm;
            var showHideElms = this.$Elm.getElements('.pwm-passwordtypes-show');

            for (var i = 0, len = showHideElms.length; i < len; i++) {
                Elm = showHideElms[i];

                new QUIButton({
                    Elm   : Elm,
                    icon  : 'fa fa-eye',
                    alt   : QUILocale.get(lg, 'controls.passwordtypes.edit.show.btn'),
                    title : QUILocale.get(lg, 'controls.passwordtypes.edit.show.btn'),
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
            }

            var rndPassElms = this.$Elm.getElements('.pwm-passwordtypes-randompassword');

            for (i = 0, len = rndPassElms.length; i < len; i++) {
                Elm = rndPassElms[i];

                new QUIButton({
                    Elm   : Elm,
                    alt   : QUILocale.get(lg, 'controls.passwordtypes.edit.rnd.btn'),
                    title : QUILocale.get(lg, 'controls.passwordtypes.edit.rnd.btn'),
                    icon  : 'fa fa-random',
                    events: {
                        onClick: function (Btn) {
                            var Elm   = Btn.getAttribute('Elm');
                            Elm.value = Math.random().toString(36).slice(-16);
                        }
                    }
                }).inject(Elm.getParent(), 'after');
            }

            this.fireEvent('loaded', [this]);
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
