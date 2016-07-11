/**
 * Select for password types
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Select
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/buttons/Select
 * @require qui/controls/loader/Loader
 * @requrie Ajax
 * @require Locale
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Select.css
 *
 * @event onLoaded [this] - fires when security classes are loaded
 */
define('package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Select', [

    'qui/QUI',
    'qui/controls/buttons/Select',
    'qui/controls/loader/Loader',

    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',

    'Locale'

], function (QUI, QUISelect, QUILoader, PasswordHandler, QUILocale) {
    "use strict";

    var lg        = 'pcsg/grouppasswordmanager';
    var Passwords = new PasswordHandler();

    return new Class({

        Extends: QUISelect,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Select',

        Binds: [
            '$onInject'
        ],

        options: {
            initialValue: false    // sets an initial value for the dropdown menu (if it exists!)
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.Loader       = new QUILoader();
            this.$initialLoad = true;
        },

        /**
         * event on DOMElement creation
         */
        create: function () {
            this.$Content = this.parent();

            this.$Content.addClass('pcsg-gpm-securityclassselect');

            this.Loader.inject(this.$Content);

            return this.$Content;
        },

        /**
         * event: on control inject
         */
        $onInject: function () {
            var self = this;

            this.Loader.show();

            Passwords.getTypes().then(function (types) {
                var first = false;

                for (var i = 0, len = types.length; i < len; i++) {
                    var t = types[i];

                    self.appendChild(
                        QUILocale.get(lg, 'passwordtypes.select.type.' + t),
                        t,
                        'fa fa-file-text-o'
                    );

                    if (first === false) {
                        first = t;
                    }
                }

                if (self.getAttribute('initialValue')) {
                    self.setValue(self.getAttribute('initialValue'));
                } else {
                    self.setValue(first);
                }

                self.Loader.hide();
            });
        }
    });

});