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
 * @event onChange [categoryIds, this] - fires if the user adds and/or deletes password categories
 * @event onRemoveCategory [categoryId, this] - fires if the user deletes a password category
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
            '$showCategoryTree',
            '$onRemoveCategory'
        ],

        options: {
            editMode: true  // lets the user add and remove categories
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject        : this.$onInject,
                onRemoveCategory: this.$onRemoveCategory
            });

            this.Loader        = new QUILoader();
            this.$categoryIds  = [];
            this.$CatContainer = null;
        },

        /**
         * event on DOMElement creation
         */
        create: function () {
            var self = this;

            this.$Elm = new Element('div', {
                'class': 'pcsg-gpm-categories-select',
                html   : '<div class="pcsg-gpm-categories-select-container"></div>'
            });

            this.$CatContainer = this.$Elm.getElement(
                '.pcsg-gpm-categories-select-container'
            );

            if (this.getAttribute('editMode')) {
                new Element('div', {
                    'class': 'pcsg-gpm-categories-select-edit',
                    html   : '<span class="pcsg-gpm-categories-select-edit-icon fa fa-plus-circle"></span>' +
                    '<span class="pcsg-gpm-categories-select-edit-text">' +
                    QUILocale.get(lg, 'controls.categories.select.add.text') +
                    '</span>'
                }).inject(
                    this.$Elm
                );

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
            } else {
                this.$Elm.getElement(
                    '.pcsg-gpm-categories-select-container'
                ).addEvents({
                    mouseenter: function (event) {
                        self.$CategoriesToolTip = new Element('div', {
                            'class': 'pcsg-gpm-tooltip',
                            styles : {
                                top  : -7,
                                left : 5,
                                float: 'left',
                                width: 400
                            },
                            html   : '<span>' +
                            QUILocale.get(lg, 'controls.password.view.categories.tooltip') +
                            '</span>'
                        }).inject(event.target, 'after');
                    },
                    mouseleave: function () {
                        if (self.$CategoriesToolTip) {
                            self.$CategoriesToolTip.destroy();
                        }
                    }
                });
            }

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
            this.$CatContainer.set('html', '');

            if (!this.$categoryIds.length) {
                this.$CatContainer.set('html', QUILocale.get(lg, 'controls.categories.select.all'));
                return;
            }

            var self = this;

            this.Loader.show();

            Categories.getPublic(this.$categoryIds).then(function (categories) {
                for (var i = 0, len = categories.length; i < len; i++) {
                    self.$getCatElm(
                        categories[i].id,
                        categories[i].title
                    ).inject(self.$CatContainer);
                }

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
                        var newCatIds = CategoryTreeCtrl.getSelectedCategoryIds();
                        self.$categoryIds.combine(newCatIds);
                        self.fireEvent('change', [self.$categoryIds, self]);
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
        },

        /**
         * Event: onRemoveCategory
         *
         * @param {Number} catId
         */
        $onRemoveCategory: function (catId) {
            this.$categoryIds.erase(catId);
            this.$refresh();
            this.fireEvent('change', [this.$categoryIds, this]);
        },

        /**
         * Get display element for a category
         *
         * @param {number} catId
         * @param {string} catTitle
         * @return {HTMLElement}
         */
        $getCatElm: function (catId, catTitle) {
            var self = this;

            var CatElm = new Element('div', {
                'class'     : 'pcsg-gpm-passwords-categories-category',
                'data-catid': catId,
                html        : '<span class="pcsg-gpm-passwords-categories-category-title">' +
                catTitle +
                '</span>'
            });

            if (!this.getAttribute('editMode')) {
                return CatElm;
            }

            new Element('span', {
                'class': 'pcsg-gpm-passwords-categories-category-remove fa fa-remove'
            }).inject(CatElm);

            CatElm.getElement(
                '.pcsg-gpm-passwords-categories-category-remove'
            ).addEvents({
                click: function (event) {
                    var catId = event.target.getParent().getProperty('data-catid');
                    self.fireEvent('removeCategory', [catId, self]);
                }
            });

            return CatElm;
        }
    });

});