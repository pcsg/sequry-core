/**
 * Control for viewing password content
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/View
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @require qui/controls/loader/Loader
 * @require Locale
 * @require package/pcsg/grouppasswordmanager/bin/classes/Authentication
 * @require package/pcsg/grouppasswordmanager/bin/classes/Passwords
 * @require package/pcsg/grouppasswordmanager/bin/controls/categories/public/Select
 * @require package/pcsg/grouppasswordmanager/bin/controls/categories/private/Select
 * @require package/pcsg/grouppasswordmanager/bin/Categories
 * @require ClipboardJS
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/password/View.css
 *
 * @event onLoaded
 * @event onClose
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/View', [

    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/loader/Loader',

    'Locale',

    'package/pcsg/grouppasswordmanager/bin/Authentication',
    'package/pcsg/grouppasswordmanager/bin/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/categories/public/Select',
    'package/pcsg/grouppasswordmanager/bin/controls/categories/private/Select',
    'package/pcsg/grouppasswordmanager/bin/Categories',
    'package/pcsg/grouppasswordmanager/bin/controls/utils/InputButtons',

    'css!package/pcsg/grouppasswordmanager/bin/controls/password/View.css'

], function (QUIControl, QUIButton, QUILoader, QUILocale, Authentication,
             Passwords, CategorySelect, CategorySelectPrivate, Categories,
             InputButtons) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/View',

        Binds: [
            '$onInject',
            '$setPrivateCategories'
        ],

        options: {
            'passwordId'          : false,   // id of the password
            'editPublicCategories': false // can edit public categories
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.Loader             = new QUILoader();
            this.$CategoriesToolTip = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-password-view',
                html   : '<p class="pcsg-gpm-password-view-payload">' +
                QUILocale.get(lg, 'password.view.restricted.info') +
                '</p>'
            });

            this.Loader.inject(this.$Elm);

            return this.$Elm;
        },

        /**
         * event : onInject
         *
         * Ask user for authentication information and load password data
         */
        $onInject: function () {
            var self = this;

            Passwords.getView(self.getAttribute('passwordId')).then(
                function (viewHtml) {
                    if (!viewHtml) {
                        return;
                    }

                    self.$Elm.set(
                        'html',
                        viewHtml
                    );

                    var CategoryPrivateElm = self.$Elm.getElement(
                        '.pcsg-gpm-password-view-info-categories-private'
                    );

                    var CategoryPrivate = new CategorySelectPrivate({
                        events: {
                            onChange: self.$setPrivateCategories
                        }
                    }).inject(CategoryPrivateElm);

                    var catIdsPrivate = CategoryPrivateElm.getProperty(
                        'data-catids'
                    );

                    if (catIdsPrivate) {
                        catIdsPrivate = catIdsPrivate.split(',');
                        CategoryPrivate.setValue(catIdsPrivate);
                    }

                    // public categories
                    var CategoriesPublicElm = self.$Elm.getElement(
                        '.pcsg-gpm-password-view-info-categories-public'
                    );

                    var Categories = new CategorySelect({
                        editMode: self.getAttribute('editPublicCategories')
                    }).inject(CategoriesPublicElm);

                    var catIdsPublic = CategoriesPublicElm.getProperty(
                        'data-catids'
                    );

                    if (catIdsPublic) {
                        catIdsPublic = catIdsPublic.split(',');
                        Categories.setValue(catIdsPublic);
                    }

                    self.$parseView();
                    self.fireEvent('loaded');
                },
                function () {
                    self.fireEvent('close');
                }
            );
        },

        /**
         * Set private password categories
         *
         * @return {Promise}
         */
        $setPrivateCategories: function (categoryIds) {
            var self = this;

            this.Loader.show();

            return new Promise(function (resolve, reject) {
                Categories.setPrivatePasswordCategories(
                    self.getAttribute('passwordId'),
                    categoryIds
                ).then(function () {
                    self.Loader.hide();

                    if (window.PasswordCategories) {
                        window.PasswordCategories.refreshCategories();
                    }

                    resolve();
                }, reject);
            });
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
