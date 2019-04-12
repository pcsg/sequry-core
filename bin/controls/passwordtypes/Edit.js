/**
 * Control for password content input with different types
 *
 * @module package/sequry/core/bin/controls/passwordtypes/Edit
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onLoaded [this]
 */
define('package/sequry/core/bin/controls/passwordtypes/Edit', [

    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/loader/Loader',

    'Ajax',
    'Locale',

    'package/sequry/core/bin/Passwords',

    'css!package/sequry/core/bin/controls/passwordtypes/Edit.css'

], function (QUIControl, QUIButton, QUILoader, QUIAjax, QUILocale, Passwords) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/sequry/core/bin/controls/passwordtypes/Edit',

        Binds: [
            '$onInject',
            '$parseTemplate'
        ],

        options: {
            type: false // initial type
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader = new QUILoader();

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

            this.Loader.inject(this.$Elm);

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
                'package_sequry_core_ajax_passwordtypes_getEditHtml',
                function (templateHtml) {
                    self.$EditContent.set('html', templateHtml);
                    //self.$EditContent.getElement('table').addClass(
                    //    'pcsg-gpm-password-payload-table'
                    //);
                    self.$parseTemplate();
                }, {
                    'package': 'sequry/core',
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
            var self         = this;
            var Elm;
            var showHideElms = this.$Elm.getElements('.gpm-passwordtypes-show');

            var FuncOnShowBtnClick = function (Btn) {
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
            };

            for (var i = 0, len = showHideElms.length; i < len; i++) {
                Elm = showHideElms[i];

                new QUIButton({
                    Elm   : Elm,
                    icon  : 'fa fa-eye',
                    alt   : QUILocale.get(lg, 'controls.passwordtypes.edit.show.btn'),
                    title : QUILocale.get(lg, 'controls.passwordtypes.edit.show.btn'),
                    action: 'show',
                    events: {
                        onClick: FuncOnShowBtnClick
                    }
                }).inject(Elm.getParent());
            }

            var rndPassElms = this.$Elm.getElements('.gpm-passwordtypes-randompassword');

            var FuncOnRndBtnClick = function (Btn) {
                var Elm = Btn.getAttribute('Elm');

                self.Loader.show();

                Passwords.generateRandomPassword().then(function (rndPassword) {
                    Elm.value = rndPassword;
                    self.Loader.hide();
                });
            };

            for (i = 0, len = rndPassElms.length; i < len; i++) {
                Elm = rndPassElms[i];

                new QUIButton({
                    Elm   : Elm,
                    alt   : QUILocale.get(lg, 'controls.passwordtypes.edit.rnd.btn'),
                    title : QUILocale.get(lg, 'controls.passwordtypes.edit.rnd.btn'),
                    icon  : 'fa fa-random',
                    events: {
                        onClick: FuncOnRndBtnClick
                    }
                }).inject(Elm.getParent());
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
