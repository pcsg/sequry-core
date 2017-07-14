/**
 * Select actors for a SecurityClass via Grid
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTablePopup
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTablePopup.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTablePopup.css
 *
 * @event onSubmit [
 */
define('package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTablePopup', [

    'qui/controls/windows/Popup',
    'package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTable'

], function (QUIPopup, SelectTable) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTablePopup',

        Binds: [
            '$onOpen',
            '$onResize'
        ],

        options: {
            securityClassId: false   // security class id the actors have to be eligible for
        },

        initialize: function (options) {
            this.parent(options);

            this.$SelectTable = null;

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * Event: onOpen
         */
        $onOpen: function () {
            //var self = this;
            //
            //this.Loader.show();

            this.$SelectTable = new SelectTable({
                securityClassId: this.getAttribute('securityClassId')
            }).inject(this.getContent());
        },

        /**
         * Event: onResize
         */
        $onResize: function () {
            if (this.$SelectTable) {
                this.$SelectTable.resize();
            }
        }
    });
});
