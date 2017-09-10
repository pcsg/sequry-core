/**
 * Parses password data called from a PasswordLink
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/link/View
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/Passwords
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/password/link/View.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/password/link/View.css
 *
 * @event onSubmit [this] - fires after a new PasswordLink has been successfully created
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/link/View', [

    'qui/controls/Control'

], function (QUIControl) {
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
            var Elm               = this.getElm();
            var passwordInputElms = Elm.getElements(
                'input[type="password"]'
            );

            passwordInputElms.forEach(function(Elm) {
                Elm.type = 'text';
            });
        }
    });
});
