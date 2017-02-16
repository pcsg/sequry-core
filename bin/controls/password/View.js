/**
 * Control for creating a new password
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/View
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/controls/passwords/SecurityClassSelect
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/password/View.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/password/View.css
 *
 * @event onLoaded
 * @event onClose
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/View', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/loader/Loader',

    'Locale',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',
    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/categories/private/Select',
    'package/pcsg/grouppasswordmanager/bin/Categories',

    'ClipboardJS',

    'css!package/pcsg/grouppasswordmanager/bin/controls/password/View.css'

], function (QUI, QUIControl, QUIButton, QUILoader, QUILocale, AuthHandler,
             PasswordHandler, CategorySelectPrivate, Categories, Clipboard) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager',
        Passwords      = new PasswordHandler(),
        Authentication = new AuthHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/View',

        Binds: [
            '$onInject',
            '$setPrivateCategories'
        ],

        options: {
            'passwordId': false   // id of the password
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.Loader = new QUILoader();
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

            Authentication.passwordAuth(this.getAttribute('passwordId')).then(function (AuthData) {
                Passwords.getView(
                    self.getAttribute('passwordId'),
                    AuthData
                ).then(
                    function (viewHtml) {
                        self.$Elm.set(
                            'html',
                            viewHtml
                        );

                        var CategoryPrivateElm = self.$Elm.getElement(
                            '.pcsg-gpm-password-view-info-categories-private'
                        );

                        var CategoryPrivate = new CategorySelectPrivate({
                            events: {
                                onCategoriesSelect: self.$setPrivateCategories
                            }
                        }).inject(CategoryPrivateElm);

                        var catIdsPrivate = CategoryPrivateElm.getProperty(
                            'data-catids'
                        );

                        if (catIdsPrivate) {
                            catIdsPrivate = catIdsPrivate.split(',');
                            CategoryPrivate.setValue(catIdsPrivate);
                        }

                        // public categories (show only!)
                        var CategoriesPublicElm = self.$Elm.getElement(
                            '.pcsg-gpm-password-view-info-categories-public'
                        );

                        var catIdsPublic = CategoriesPublicElm.getProperty(
                            'data-catids'
                        );

                        if (catIdsPublic) {
                            catIdsPublic = catIdsPublic.split(',');

                            Categories.getPublic(catIdsPublic).then(function(categories) {
                                var titles = [];

                                for (var i = 0, len = categories.length; i < len; i++) {
                                    titles.push(categories[i].title);
                                }

                                new Element('span', {
                                    html: titles.join(', ')
                                }).inject(CategoriesPublicElm);
                            });
                        } else {
                            new Element('span', {
                                html: QUILocale.get(lg, 'controls.categories.map.category.all')
                            }).inject(CategoriesPublicElm);
                        }

                        self.$parseView();
                        self.fireEvent('loaded');
                    },
                    function () {
                        self.fireEvent('close');
                    }
                );
            }, function () {
                self.fireEvent('close');
            });
        },

        /**
         * Set private password categories
         *
         * @return {Promise}
         */
        $setPrivateCategories: function(categoryIds) {
            var self = this;

            this.Loader.show();

            return new Promise(function(resolve, reject) {
                Categories.setPrivatePasswordCategories(
                    self.getAttribute('passwordId'),
                    categoryIds
                ).then(function() {
                    self.Loader.hide();
                    resolve();
                }, reject);
            });
        },

        /**
         * Parse DOM elements of the view and add specific controls (e.g. copy / show password buttons)
         */
        $parseView: function () {
            // copy elements
            var i, len, Elm, ValueInput, CopyBtn;
            var copyElms = this.$Elm.getElements('.pwm-passwordtypes-copy');

            for (i = 0, len = copyElms.length; i < len; i++) {
                Elm = copyElms[i];

                CopyBtn = new QUIButton({
                    Elm   : Elm,
                    icon  : 'fa fa-copy',
                    events: {
                        onClick: function (Btn) {
                            var Elm = Btn.getAttribute('Elm');
                            Elm.select();
                        }
                    }
                }).inject(Elm, 'after');

                new Clipboard(CopyBtn.getElm(), {
                    text: function() {
                        return this.getAttribute('Elm').value;
                    }.bind(CopyBtn)
                });
            }

            // show elements (switch between show and hide)
            var showElms = this.$Elm.getElements('.pwm-passwordtypes-show');

            for (i = 0, len = showElms.length; i < len; i++) {
                Elm = showElms[i];

                new QUIButton({
                    Elm   : Elm,
                    icon  : 'fa fa-eye',
                    action: 'show',
                    events: {
                        onClick: function (Btn) {
                            var Elm = Btn.getAttribute('Elm');

                            if (Btn.getAttribute('action') === 'show') {
                                Btn.setAttributes({
                                    icon  : 'fa fa-eye-slash',
                                    action: 'hide'
                                });

                                Elm.setProperty('type', 'text');
                                Elm.focus();
                                Elm.select();

                                return;
                            }

                            Btn.setAttributes({
                                icon  : 'fa fa-eye',
                                action: 'show'
                            });

                            Elm.setProperty('type', 'password');
                            Elm.blur();
                        }
                    }
                }).inject(Elm, 'after');
            }
        }
    });
});
