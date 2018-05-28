/**
 * Basic registration parent control for AuthPlugins
 *
 * @module package/sequry/core/bin/controls/authPlugins/Registration
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/sequry/core/bin/controls/authPlugins/Registration', [

    'qui/controls/Control'

], function (QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/sequry/core/bin/controls/authPlugins/Registration',

        Binds: [
            '$onImport',
            'focus',
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
         * Return authentication information
         *
         * @return {string}
         */
        getAuthData: function () {
            return this.$Input.value;
        }
    });
});
