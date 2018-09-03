/**
 * Control for viewing password content
 *
 * @module package/sequry/core/bin/controls/password/View
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onLoaded [this]
 * @event onClose [this]
 */
define('package/sequry/core/bin/controls/password/View', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',

    'Locale',

    'package/sequry/core/bin/Actors',
    'package/sequry/core/bin/Passwords',
    'package/sequry/core/bin/controls/categories/public/Select',
    'package/sequry/core/bin/controls/categories/private/Select',
    'package/sequry/core/bin/Categories',
    'package/sequry/core/bin/controls/utils/InputButtons',

    'Mustache',

    'text!package/sequry/core/bin/controls/password/View.html',
    'css!package/sequry/core/bin/controls/password/View.css'

], function (QUIControl, QUILoader, QUILocale, Actors, Passwords, CategorySelect, CategorySelectPrivate,
             Categories, InputButtons, Mustache, template) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/sequry/core/bin/controls/password/View',

        Binds: [
            '$onInject',
            '$setPrivateCategories',
            '$onMenuItemClick'
        ],

        options: {
            passwordId          : false,   // id of the password
            editPublicCategories: false // can edit public categories
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.$PayloadContent = null;
            this.$Menu           = null;
            this.Loader          = new QUILoader();
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            var lgPrefix = 'controls.passsword.view.template.';

            this.$Elm.set({
                'class': 'sequry-core-password-view',
                html   : Mustache.render(template, {
                    loadingInfo: QUILocale.get(lg, lgPrefix + 'loadingInfo')
                })
            });

            this.$Menu           = this.$Elm.getElement('.sequry-core-password-view-menu');
            this.$PayloadContent = this.$Elm.getElement('.sequry-core-password-view-content');

            this.$Menu.getElements('.sequry-core-password-view-menu-entry').addEvent('click', this.$onMenuItemClick);

            this.Loader.inject(this.$Elm);

            return this.$Elm;
        },

        /**
         * event : onInject
         *
         * Ask user for authentication information and load password data
         */
        $onInject: function () {
            var self       = this;
            var passwordId = this.getAttribute('passwordId');

            Actors.getPasswordAccessInfo(passwordId).then(function (AccessInfo) {
                if (!AccessInfo.canAccess) {
                    Passwords.getNoAccessInfoElm(AccessInfo, self).inject(self.$Elm);
                    self.fireEvent('loaded');
                    return;
                }

                Passwords.getView(passwordId).then(function (ViewData) {
                        if (!ViewData) {
                            return;
                        }

                        self.$PayloadContent.set(
                            'html',
                            ViewData.viewHtml
                        );

                        // enable menu buttons
                        for (var i = 0, len = ViewData.permissions.length; i < len; i++) {
                            var MenuBtn = self.$Menu.getElement(
                                '.sequry-core-password-view-menu-entry[data-action="' + ViewData.permissions[i] + '"]'
                            );

                            if (MenuBtn) {
                                MenuBtn.removeClass('sequry-core-password-view-menu-entry-locked');
                            }
                        }

                        // category handling

                        var CategoryPrivateElm = self.$Elm.getElement(
                            '.sequry-core-password-view-info-categories-private'
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
                            '.sequry-core-password-view-info-categories-public'
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
                        self.fireEvent('loaded', [self]);
                    },
                    function () {
                        self.fireEvent('close', [self]);
                    }
                );
            });
        },

        /**
         * Event: onClick of a Password action menu item
         *
         * @param {Object} event
         */
        $onMenuItemClick: function (event) {
            var MenuItem;
            var passwordId = this.getAttribute('passwordId');

            if (event.target.hasClass('sequry-core-password-view-menu-entry')) {
                MenuItem = event.target;
            } else {
                MenuItem = event.target.getParent('.sequry-core-password-view-menu-entry');
            }

            switch (MenuItem.get('data-action')) {
                case 'edit':
                    window.PasswordList.editPassword(passwordId);
                    break;

                case 'share':
                    window.PasswordList.sharePassword(passwordId);
                    break;

                case 'link':
                    window.PasswordList.linkPassword(passwordId);
                    break;

                case 'delete':
                    window.PasswordList.deletePassword(passwordId);
                    break;
            }

            this.fireEvent('close', [this]);
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
