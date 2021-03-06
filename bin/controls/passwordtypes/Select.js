/**
 * Select for password types
 *
 * @module package/sequry/core/bin/controls/passwordtypes/Select
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onLoaded [this] - fires when security classes are loaded
 */
define('package/sequry/core/bin/controls/passwordtypes/Select', [

    'qui/QUI',
    'qui/controls/buttons/Select',
    'qui/controls/loader/Loader',

    'package/sequry/core/bin/classes/Passwords'

], function (QUI, QUISelect, QUILoader, PasswordHandler) {
    "use strict";

    var Passwords = new PasswordHandler();

    return new Class({

        Extends: QUISelect,
        Type   : 'package/sequry/core/bin/controls/passwordtypes/Select',

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
                        t.title,
                        t.name,
                        'fa fa-file-text-o'
                    );

                    if (first === false) {
                        first = t.name;
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