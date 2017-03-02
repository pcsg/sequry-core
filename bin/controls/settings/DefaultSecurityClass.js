/**
 * Select the default security class for new passwords
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/settings/DefaultSecurityClass
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/Control
 * @require package/pcsg/grouppasswordmanager/bin/classes/Locations
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/settings/DefaultSecurityClass.css
 */
define('package/pcsg/grouppasswordmanager/bin/controls/settings/DefaultSecurityClass', [

    'qui/controls/Control',
    'package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select',

    //'css!package/pcsg/grouppasswordmanager/bin/controls/settings/DefaultSecurityClass.css'

], function (QUIControl, SecurityClassSelect) {
    "use strict";

    return new Class({
        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/settings/DefaultSecurityClass',

        Binds: [
            '$onImport',
            '$setSettings'
        ],

        options: {},

        initialize: function (options) {
            this.parent(options);

            this.$Input  = null;
            this.$Select = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * Event: onImport
         */
        $onImport: function () {
            var self = this;

            this.$Input = this.getElm();
            this.$Input.setStyle('display', 'none');

            var value = this.$Input.value;

            this.$Select = new SecurityClassSelect({
                events: {
                    onChange: function (value) {
                        self.$Input.value = value;
                    },
                    onLoaded: function () {
                        if (value) {
                            self.$Select.setValue(value);
                        }
                    }
                }
            }).inject(
                this.$Input,
                'after'
            );

            this.$Select.getElm().addClass('field-container-field');
        }
    });
});