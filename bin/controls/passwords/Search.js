/**
 * Control for creating a new password
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/passwords/Search
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onLoaded
 * @event onSubmit [SearchData]
 */
define('package/pcsg/grouppasswordmanager/bin/controls/passwords/Search', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/utils/Form',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/Passwords',

    'text!package/pcsg/grouppasswordmanager/bin/controls/passwords/Search.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/passwords/Search.css'

], function (QUI, QUIControl, QUIFormUtils, QUILocale, Mustache, Passwords, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/passwords/Search',

        Binds: [
            '$onInject',
            'submit'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.$PasswordData = null;
            this.$owner        = false;
            this.$TypeSelect   = null;
            this.$Form         = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            var self      = this;
            var lg_prefix = 'password.search.template.';

            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-password-search',
                html   : Mustache.render(template, {
                    title                  : QUILocale.get(lg, lg_prefix + 'title'),
                    searchData             : QUILocale.get(lg, lg_prefix + 'searchData'),
                    searchterm             : QUILocale.get(lg, lg_prefix + 'searchterm'),
                    searchfields           : QUILocale.get(lg, lg_prefix + 'searchfields'),
                    passwordTypes          : QUILocale.get(lg, lg_prefix + 'passwordTypes'),
                    passwordTypesOptionAll : QUILocale.get(lg, lg_prefix + 'passwordTypesOptionAll'),
                    searchfieldsTitle      : QUILocale.get(lg, lg_prefix + 'searchfieldsTitle'),
                    searchfieldsDescription: QUILocale.get(lg, lg_prefix + 'searchfieldsDescription'),
                    filters                : QUILocale.get(lg, lg_prefix + 'filters'),
                    uncategorized          : QUILocale.get(lg, lg_prefix + 'uncategorized'),
                    uncategorizedPrivate   : QUILocale.get(lg, lg_prefix + 'uncategorizedPrivate')
                })
            });

            this.$Form = this.$Elm.getElement('form');

            this.$Form.addEvents({
                submit: function (event) {
                    if (typeof event !== 'undefined') {
                        event.stop();
                    }

                    self.submit();
                }
            });

            this.$TypeSelect = this.$Elm.getElement(
                'select[name="passwordtypes"]'
            );

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            var self = this;

            this.$Elm.getElement('input[name="searchterm"]').focus();

            Passwords.getTypes().then(function (types) {
                for (var i = 0, len = types.length; i < len; i++) {
                    var Type = types[i];

                    new Element('option', {
                        value: Type.name,
                        html : Type.title
                    }).inject(self.$TypeSelect);
                }

                self.fireEvent('loaded');
            });
        },

        /**
         * Submit search
         *
         * @return {Object} - Search data
         */
        submit: function () {
            var types      = [];
            var options    = this.$TypeSelect.getElements('option');
            var SearchData = QUIFormUtils.getFormData(this.$Form);

            for (var i = 0, len = options.length; i < len; i++) {
                if (!options[i].selected) {
                    continue;
                }

                types.push(options[i].value);
            }

            SearchData.passwordtypes = types;

            this.fireEvent('submit', [SearchData]);

            return SearchData;
        }
    });
});
