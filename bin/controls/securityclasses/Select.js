/**
 * Select for password security classes
 *
 * @module package/sequry/core/bin/controls/securityclasses/Select
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/buttons/Select
 * @require qui/controls/loader/Loader
 * @requrie Ajax
 * @require Locale
 * @require css!package/sequry/core/bin/controls/securityclasses/Select.css
 *
 * @event onLoaded [this] - fires when security classes are loaded
 */
define('package/sequry/core/bin/controls/securityclasses/Select', [

    'qui/QUI',
    'qui/controls/buttons/Select',
    'qui/controls/loader/Loader',

    'package/sequry/core/bin/classes/Authentication',

    'Ajax',
    'Locale'

    //'css!package/sequry/core/bin/controls/securityclasses/Select.css'

], function (QUI, QUISelect, QUILoader, AuthHandler, Ajax, QUILocale) {
    "use strict";

    var lg             = 'sequry/core';
    var Authentication = new AuthHandler();

    return new Class({

        Extends: QUISelect,
        Type   : 'package/sequry/core/bin/controls/securityclasses/Select',

        Binds: [
            '$onInject',
            '$onRefresh',
            '$onResize',
            'refresh'
        ],

        options: {
            initialValue: false    // sets an initial value for the dropdown menu (if it exists!)
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject : this.$onInject,
                onRefresh: this.$onRefresh,
                onResize : this.$onResize
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

        $onInject: function () {
            this.refresh();
        },

        $onRefresh: function () {
            this.refresh();
        },

        $onResize: function () {
            // nothing
        },

        /**
         * Refreshes the control and reloads category list
         */
        refresh: function () {
            var self = this;

            this.Loader.show();

            Authentication.getSecurityClasses().then(function (SecurityClasses) {
                var first = false;

                self.clear();

                for (var id in SecurityClasses) {
                    if (!SecurityClasses.hasOwnProperty(id)) {
                        continue;
                    }

                    var Info = SecurityClasses[id];

                    self.appendChild(
                        Info.title,
                        id,
                        'fa fa-lock'
                    );

                    if (!first) {
                        first = id;
                    }
                }

                if (self.getAttribute('initialValue')) {
                    self.setValue(self.getAttribute('initialValue'));
                } else {
                    self.setValue(first);
                }

                self.Loader.hide();

                if (self.$initialLoad) {
                    self.fireEvent('loaded', [ self ]);
                    self.$initialLoad = false;
                }
            });
        }
    });

});