/**
 * Password listing
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/categories/Panel
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @requrie Ajax
 * @require Locale
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/categories/Panel.css
 */
define('package/pcsg/grouppasswordmanager/bin/controls/categories/Panel', [

    'qui/controls/desktop/Panel',
    'qui/controls/loader/Loader',

    'qui/controls/windows/Confirm',
    'qui/controls/sitemap/Map',
    'qui/controls/sitemap/Item',
    'qui/controls/contextmenu/Item',
    'qui/controls/contextmenu/Seperator',

    'package/pcsg/grouppasswordmanager/bin/controls/categories/public/Map',
    'package/pcsg/grouppasswordmanager/bin/controls/categories/private/Map',
    'package/pcsg/grouppasswordmanager/bin/controls/categories/Filters',

    'Ajax',
    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/categories/Panel.css'

], function (QUIPanel, QUILoader, QUIConfirm, QUISiteMap, QUISiteMapItem,
             QUIContextMenuItem, QUIContextMenuSeparator, CategoryMap,
             CategoryMapPrivate, Filters, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/categories/Panel',

        Binds: [
            '$onInject',
            '$onRefresh',
            '$onCreate',
            '$onResize',
            '$buildCategoryTree',
            '$addCategoryDialog',
            '$deleteCategoryDialog',
            'getCategory'
        ],

        options: {
            icon: 'fa fa-book'
        },

        initialize: function (options) {
            this.setAttribute('title', QUILocale.get(lg, 'controls.categories.panel.title'));

            this.parent(options);

            this.addEvents({
                onInject: this.$onInject,
                onCreate: this.$onCreate
            });

            this.Loader                = new QUILoader();
            this.$selectedCatId        = null;
            this.$selectedCatIdPrivate = null;
            this.$CategoryMap          = null;
            this.$CategoryMapPrivate   = null;
            this.$FlatCategoryTree     = {};
            this.$filters              = [];
        },

        /**
         * event on DOMElement creation
         */
        $onCreate: function () {
            var self    = this;
            var Content = this.getContent();

            this.getElm().addClass('pcsg-gpm-categories-panel');

            Content.setStyles({
                padding: 0
            });

            Content.set(
                'html',
                '<h1>' + QUILocale.get(lg, 'controls.categories.panel.public.title') + '</h1>' +
                '<div class="pcsg-gpm-passwords-categories-list-public"></div>' +
                '<h1>' + QUILocale.get(lg, 'controls.categories.panel.private.title') + '</h1>' +
                '<div class="pcsg-gpm-passwords-categories-list-private"></div>' +
                '<h1>' + QUILocale.get(lg, 'controls.categories.panel.filter.title') + '</h1>' +
                '<div class="pcsg-gpm-passwords-categories-filter"></div>'
            );

            this.Loader.inject(Content);

            // category map
            this.$CategoryMap = new CategoryMap({
                editMode: true,
                events  : {
                    onCategorySelect: function (catId) {
                        self.$selectedCatId        = catId;
                        self.$selectedCatIdPrivate = false;
                        self.$CategoryMapPrivate.deselectAll();
                        self.$search();
                    }
                }
            }).inject(
                Content.getElement(
                    '.pcsg-gpm-passwords-categories-list-public'
                )
            );

            // category map (private)
            this.$CategoryMapPrivate = new CategoryMapPrivate({
                editMode: true,
                events  : {
                    onCategorySelect: function (catId) {
                        self.$selectedCatIdPrivate = catId;
                        self.$selectedCatId        = false;
                        self.$CategoryMap.deselectAll();
                        self.$search();
                    }
                }
            }).inject(
                Content.getElement(
                    '.pcsg-gpm-passwords-categories-list-private'
                )
            );

            // filters
            if (this.getAttribute('selectOnlyMode')) {
                return;
            }

            this.$Filters = new Filters({
                events: {
                    onChange: function (selectedFilters) {
                        self.$filters = selectedFilters;
                        self.$search();
                    }
                }
            }).inject(Content.getElement(
                '.pcsg-gpm-passwords-categories-filter'
            ));
        },

        $onInject: function () {
        },

        /**
         * Start password search based on selected category and filters
         */
        $search: function () {
            if (!window.PasswordList) {
                return;
            }

            if (this.$selectedCatIdPrivate) {
                window.PasswordList.setSearchCategoryPrivate(this.$selectedCatIdPrivate);
            } else {
                window.PasswordList.setSearchCategory(this.$selectedCatId);
            }

            if (this.$filters.length) {
                window.PasswordList.setSearchFilters(this.$filters);
            } else {
                window.PasswordList.removeSearchFilters();
            }

            window.PasswordList.refresh();
        }
    });
});