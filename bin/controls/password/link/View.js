/**
 * Display password data from a PasswordLink
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/link/View
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/link/View', [

    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'package/pcsg/grouppasswordmanager/bin/controls/utils/InputButtons',
    'Locale'

], function (QUIControl, QUIButton, InputButtons, QUILocale) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/link/View',

        Binds: [
            '$onImport'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            })
        },

        /**
         * Event: onImport
         *
         * @return {HTMLDivElement}
         */
        $onImport: function () {
            this.$Elm = this.getElm();
            this.$parseView();
        },

        /**
         * Parse DOM elements of the view and add specific controls (e.g. copy / show password buttons)
         */
        $parseView: function () {
            var i, len;
            var ButtonParser = new InputButtons();

            // copy elements
            var copyElms = this.$Elm.getElements('.gpm-passwordtypes-copy');

            for (i = 0, len = copyElms.length; i < len; i++) {
                ButtonParser.parse(copyElms[i], ['copy']);
            }

            // show elements (switch between show and hide)
            var showElms = this.$Elm.getElements('.gpm-passwordtypes-show');

            for (i = 0, len = showElms.length; i < len; i++) {
                ButtonParser.parse(showElms[i], ['viewtoggle']);
            }

            // url elements
            var urlElms = this.$Elm.getElements('.gpm-passwordtypes-url');

            for (i = 0, len = urlElms.length; i < len; i++) {
                ButtonParser.parse(urlElms[i], ['openurl']);
            }
        }
    });
});
