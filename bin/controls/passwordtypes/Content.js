/**
 * Control for password content input with different types
 *
 * @module package/sequry/core/bin/controls/passwordtypes/Content
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onLoaded
 */
define('package/sequry/core/bin/controls/passwordtypes/Content', [

    'qui/controls/Control',
    'package/sequry/core/bin/controls/passwordtypes/Select',

    'css!package/sequry/core/bin/controls/passwordtypes/Content.css'

], function (QUIControl, TypeSelect) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/sequry/core/bin/controls/passwordtypes/Content',

        Binds: [
            '$loadContent',
            '$onDestroy'
        ],

        options: {
            type: false // initial password type
        },

        initialize: function (options) {
            this.parent(options);

            this.$PasswordTypeControl = null;
            this.$ContentElm          = null;
            this.$passwordType        = null;
            this.$CurrentData         = {};
            this.$loaded              = false;

            this.addEvents({
                onDestroy: this.$onDestroy
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
                'class': 'pcsg-gpm-password-typecontent',
                html   : '<div class="pcsg-gpm-password-typecontent-select"></div>' +
                    '<div class="pcsg-gpm-password-typecontent-content"></div>'
            });

            this.$ContentElm = this.$Elm.getElement('.pcsg-gpm-password-typecontent-content');

            var TypeSelectElm = this.$Elm.getElement('.pcsg-gpm-password-typecontent-select');

            new TypeSelect({
                initialValue: this.getAttribute('type'),
                events      : {
                    onChange: this.$loadContent
                }
            }).inject(TypeSelectElm);

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
            this.$CurrentData = Object.merge(this.$CurrentData, this.getData());

            require([
                'package/sequry/core/bin/controls/passwordtypes/Edit'
            ], function (PasswordTypeControl) {
                self.$PasswordTypeControl = new PasswordTypeControl({
                    type  : type,
                    events: {
                        onLoaded: function () {
                            if (!self.$loaded) {
                                self.fireEvent('loaded');
                                self.$loaded = true;
                            }

                            if (Object.getLength(self.$CurrentData)) {
                                self.setData(self.$CurrentData);
                            }
                        }
                    }
                }).inject(self.$ContentElm);

                self.$passwordType = type;
            });
        },

        /**
         * Set content to current control
         *
         * @param {Object} Content
         */
        setData: function (Content) {
            if (!this.$PasswordTypeControl) {
                return;
            }

            this.$PasswordTypeControl.setData(Content);
        },

        /**
         * Get content from current control
         *
         * @return {Object}
         */
        getData: function () {
            if (!this.$PasswordTypeControl) {
                return;
            }

            return this.$PasswordTypeControl.getData();
        },

        /**
         * Return currently selected password type
         *
         * @returns {string}
         */
        getPasswordType: function () {
            return this.$passwordType;
        },

        /**
         * Event: onDestroy
         */
        $onDestroy: function () {
            this.$CurrentData = null;
        }
    });
});
