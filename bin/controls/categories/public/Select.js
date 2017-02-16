/**
 * Select a password category
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/categories/public/Select
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/buttons/Select
 * @require qui/controls/loader/Loader
 * @requrie Ajax
 * @require Locale
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/categories/public/Select.css
 *
 * @event onLoaded [this] - fires when security classes are loaded
 * @event onCategoriesSelect [categoryIds, this] - fires if the user selects categories
 */
define('package/pcsg/grouppasswordmanager/bin/controls/categories/public/Select', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/loader/Loader',

    'qui/controls/windows/Confirm',
    'package/pcsg/grouppasswordmanager/bin/controls/categories/public/Map',
    'package/pcsg/grouppasswordmanager/bin/Categories',

    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/categories/public/Select.css'

], function (QUI, QUIControl, QUIButton, QUILoader, QUIConfirm, CategoryTree,
             Categories, QUILocale) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/categories/public/Select',

        Binds: [
            'getValue',
            'setValue',
            '$refresh',
            '$showCategoryTree'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.Loader       = new QUILoader();
            this.$categoryIds = [];
            this.$TitleElm    = null;
        },

        /**
         * event on DOMElement creation
         */
        create: function () {
            var self = this;

            this.$Elm = new Element('div', {
                'class': 'pcsg-gpm-categories-select',
                html   : '<span class="pcsg-gpm-categories-select-title"></span>' +
                '<span class="pcsg-gpm-categories-select-edit fa fa-edit"></span>'
            });

            var EditElm = this.$Elm.getElement(
                '.pcsg-gpm-categories-select-edit'
            );

            EditElm.addEvents({
                click: self.$showCategoryTree
            });

            EditElm.setProperties({
                title: QUILocale.get(lg, 'controls.categories.select.title'),
                alt  : QUILocale.get(lg, 'controls.categories.select.title')
            });

            this.$TitleElm = this.$Elm.getElement(
                '.pcsg-gpm-categories-select-title'
            );

            this.Loader.inject(this.$Elm);

            return this.$Elm;
        },

        /**
         * Event: onInject
         */
        $onInject: function () {
            this.$refresh();
        },

        /**
         * Refresh category label
         */
        $refresh: function () {
            if (!this.$categoryIds.length) {
                this.$TitleElm.set('html', QUILocale.get(lg, 'controls.categories.category.all'));
                return;
            }

            var self = this;

            this.Loader.show();

            Categories.getPublic(this.$categoryIds).then(function (categories) {
                var titles = [];

                for (var i = 0, len = categories.length; i < len; i++) {
                    titles.push(categories[i].title);
                }

                self.$TitleElm.set('html', titles.join(', '));
                self.Loader.hide();
            });
        },

        /**
         * Show category tree
         */
        $showCategoryTree: function () {
            var self = this;
            var CategoryTreeCtrl;

            // open popup
            var Popup = new QUIConfirm({
                'class'    : 'pcsg-gpm-passwords-categories-select',
                'maxHeight': 600,
                maxWidth   : 400,
                'autoclose': true,

                'title'   : QUILocale.get(lg, 'controls.categories.select.title'),
                'texticon': 'fa fa-book',
                'icon'    : 'fa fa-book',

                buttons: true,

                events: {
                    onOpen  : function () {
                        var Content = Popup.getContent();

                        Content.set(
                            'html',
                            '<span>' + QUILocale.get(lg, 'controls.categories.select.info') + '</span>'
                        );

                        CategoryTreeCtrl = self.$getCategoryTreeControl().inject(Content);
                    },
                    onSubmit: function () {
                        self.$categoryIds = CategoryTreeCtrl.getSelectedCategoryIds();
                        self.fireEvent('categoriesSelect', [self.$categoryIds, self]);
                        self.$refresh();

                        Popup.close();
                    }
                }
            });

            Popup.open();
        },

        /**
         * Get category tree control
         *
         * @return {Object} - CategoryTree
         */
        $getCategoryTreeControl: function () {
            return new CategoryTree({
                editMode: false
            });
        },

        /**
         * Set category IDs
         *
         * @param {Array} catIds - categories to set as value
         */
        setValue: function (catIds) {
            var self = this;

            if (!catIds.length) {
                self.$refresh();
                return;
            }

            Categories.getPublic(catIds).then(function (categories) {
                for (var i = 0, len = categories.length; i < len; i++) {
                    self.$categoryIds.push(categories[i].id);
                }

                self.$refresh();
            });
        },

        /**
         * Get ID of currently selected category
         *
         * @return {false|number} - false if no category selected yet, ID otherwise
         */
        getValue: function () {
            return this.$categoryIds;
        }
    });

});