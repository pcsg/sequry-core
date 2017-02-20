/**
 * Panel that combines different password list filters
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/categories/Panel
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/desktop/Panel
 * @require qui/controls/loader/Loader
 * @require qui/controls/windows/Confirm
 * @require qui/controls/sitemap/Map
 * @require qui/controls/sitemap/Item
 * @require qui/controls/contextmenu/Item
 * @require qui/controls/contextmenu/Seperator
 * @require package/pcsg/grouppasswordmanager/bin/controls/categories/public/Map
 * @require package/pcsg/grouppasswordmanager/bin/controls/categories/private/Map
 * @require package/pcsg/grouppasswordmanager/bin/controls/categories/Filters
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
            '$saveToggleStatus',
            '$restoreToggleStatus',
            '$search'
        ],

        options: {
            icon: 'fa fa-book'
        },

        initialize: function (options) {
            this.setAttribute('title', QUILocale.get(lg, 'controls.categories.panel.title'));

            this.parent(options);

            this.addEvents({
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
                '<div data-type="public"  data-open="1" class="pcsg-gpm-passwords-categories-list">' +
                '<h1>' +
                QUILocale.get(lg, 'controls.categories.panel.public.title') +
                '<span class="pcsg-gpm-categories-panel-toggle fa fa-minus-square-o"></span>' +
                '</h1>' +
                '<div class="pcsg-gpm-passwords-categories-list-container"></div>' +
                '</div>' +
                '<div data-type="private" data-open="1" class="pcsg-gpm-passwords-categories-list">' +
                '<h1>' +
                QUILocale.get(lg, 'controls.categories.panel.private.title') +
                '<span class="pcsg-gpm-categories-panel-toggle fa fa-minus-square-o"></span>' +
                '</h1>' +
                '<div class="pcsg-gpm-passwords-categories-list-container"></div>' +
                '</div>' +
                '<div data-type="filters" data-open="1" class="pcsg-gpm-passwords-categories-list">' +
                '<h1>' +
                QUILocale.get(lg, 'controls.categories.panel.filter.title') +
                '<span class="pcsg-gpm-categories-panel-toggle fa fa-minus-square-o"></span>' +
                '</h1>' +
                '<div class="pcsg-gpm-passwords-categories-list-container"></div>' +
                '</div>'
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
                    },
                    onLoaded: function(Map) {
                        Map.select(false);
                    }
                }
            }).inject(
                Content.getElement(
                    '.pcsg-gpm-passwords-categories-list[data-type="public"] .pcsg-gpm-passwords-categories-list-container'
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
                    '.pcsg-gpm-passwords-categories-list[data-type="private"] .pcsg-gpm-passwords-categories-list-container'
                )
            );

            // filters
            if (this.getAttribute('selectOnlyMode')) {
                return;
            }

            new Filters({
                events: {
                    onChange: function (selectedFilters) {
                        self.$filters = selectedFilters;
                        self.$search();
                    }
                }
            }).inject(Content.getElement(
                '.pcsg-gpm-passwords-categories-list[data-type="filters"] .pcsg-gpm-passwords-categories-list-container'
            ));

            // category toggle
            Content.getElements(
                '.pcsg-gpm-categories-panel-toggle'
            ).each(function (ToggleElm) {
                ToggleElm.addEvents({
                    click: function (event) {
                        var Elm       = event.target.getParent('.pcsg-gpm-passwords-categories-list');
                        var Container = Elm.getElement('.pcsg-gpm-passwords-categories-list-container');

                        var open = parseInt(Elm.getProperty('data-open'));

                        if (!open) {
                            event.target.addClass('fa-minus-square-o');
                            event.target.removeClass('fa-plus-square-o');
                            Container.setStyle('display', '');
                            Elm.setProperty('data-open', '1');
                        } else {
                            event.target.removeClass('fa-minus-square-o');
                            event.target.addClass('fa-plus-square-o');
                            Container.setStyle('display', 'none');
                            Elm.setProperty('data-open', '0');
                        }
                        
                        self.$saveToggleStatus();
                    }
                });
            });

            this.$restoreToggleStatus();
        },

        /**
         * Saves status of which parts (category, private category, filters) the user toggled
         */
        $saveToggleStatus: function () {
            var closedElms = this.$Elm.getElements(
                '.pcsg-gpm-passwords-categories-list[data-open="0"]'
            );

            var closedTypes = [];

            closedElms.each(function (Elm) {
                closedTypes.push(Elm.getProperty('data-type'));
            });

            localStorage.setItem(
                'pcsg-gpm-passwords-categories-toggleTypes',
                JSON.encode(closedTypes)
            );
        },

        /**
         * Restore toggle status from localStorage
         */
        $restoreToggleStatus: function() {
            var toggleTypes = localStorage.getItem(
                'pcsg-gpm-passwords-categories-toggleTypes'
            );

            if (!toggleTypes) {
                return;
            }

            toggleTypes = JSON.decode(toggleTypes);

            for (var i = 0, len = toggleTypes.length; i < len; i++) {
                var OpenElm = this.$Elm.getElement(
                    '.pcsg-gpm-passwords-categories-list[data-type="' + toggleTypes[i] + '"]'
                );

                if (!OpenElm) {
                    continue;
                }

                if (OpenElm.getProperty('data-open') == '1') {
                    OpenElm.getElement(
                        '.pcsg-gpm-categories-panel-toggle'
                    ).click();
                }
            }
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