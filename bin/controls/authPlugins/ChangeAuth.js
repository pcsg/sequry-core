/**
 * Basic authentication parent control for AuthPlugins
 *
 * @module package/sequry/core/bin/controls/authPlugins/ChangeAuth
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/sequry/core/bin/controls/authPlugins/ChangeAuth', [

    'qui/controls/Control'

], function (QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/sequry/core/bin/controls/authPlugins/ChangeAuth',

        Binds: [
            '$onImport',
            'focus',
            'enable',
            'disable',
            'getAuthData'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Input = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * Event: onImport
         */
        $onImport: function () {
            this.$Input      = this.getElm();
            this.$Input.type = 'hidden';
        },

        /**
         * Focus the element for authentication data input
         */
        focus: function () {
            // to be implemented by class
        },

        /**
         * Enable the element for authentication data input
         */
        enable: function () {
            // to be implemented by class
        },

        /**
         * Disable the element for authentication data input
         */
        disable: function () {
            // to be implemented by class
        },

        /**
         * Return authentication information
         *
         * @return {string}
         */
        getAuthData: function () {
            return this.$Input.value;
        }
    });
});
