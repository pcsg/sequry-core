/**
 * Basic authentication parent control for AuthPlugins
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/authPlugins/Authentication
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/Control
 *
 * @event onSubmit [authData, self] - fires if the user submits the authentication data
 */
define('package/pcsg/grouppasswordmanager/bin/controls/authPlugins/Authentication', [

    'qui/controls/Control'

], function (QUIControl) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/authPlugins/Authentication',

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
