/**
 * Panel that combines different password list filters
 *
 * @module package/sequry/core/bin/controls/categories/Panel
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/sequry/core/bin/controls/categories/Panel', [

    'qui/controls/desktop/Panel',
    'qui/controls/loader/Loader',

    'qui/controls/windows/Confirm',
    'qui/controls/sitemap/Map',
    'qui/controls/sitemap/Item',
    'qui/controls/contextmenu/Item',
    'qui/controls/contextmenu/Separator',

    'package/sequry/core/bin/controls/categories/public/Map',
    'package/sequry/core/bin/controls/categories/private/Map',
    'package/sequry/core/bin/controls/categories/Filters',
    'package/sequry/core/bin/Passwords',

    'Ajax',
    'Locale',

    'css!package/sequry/core/bin/controls/categories/Panel.css'

], function (QUIPanel, QUILoader, QUIConfirm, QUISiteMap, QUISiteMapItem,
             QUIContextMenuItem, QUIContextMenuSeparator, CategoryMap,
             CategoryMapPrivate, Filters, Passwords, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/sequry/core/bin/controls/categories/Panel',

        Binds: [
            '$saveToggleStatus',
            '$restoreToggleStatus',
            '$search',
            '$onInject',
            'refresh',
            '$onDestroy'
        ],

        options: {
            icon: 'fa fa-book'
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onCreate : this.$onCreate,
                onInject : this.$onInject,
                onDestroy: this.$onDestroy
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
                    onLoaded        : function (Map) {
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

            window.PasswordCategories = this;
        },

        /**
         * Event: onInject
         */
        $onInject: function () {
            this.setAttribute('title', QUILocale.get(lg, 'controls.categories.panel.title'));
            this.refresh();
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
        $restoreToggleStatus: function () {
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
            var self = this;

            Passwords.openPasswordListPanel().then(function (PasswordList) {
                if (self.$selectedCatIdPrivate) {
                    PasswordList.setSearchCategoryPrivate(self.$selectedCatIdPrivate);
                } else {
                    PasswordList.setSearchCategory(self.$selectedCatId);
                }

                if (self.$filters) {
                    PasswordList.setSearchFilters(self.$filters);
                } else {
                    PasswordList.removeSearchFilters();
                }

                PasswordList.refresh();
            });
        },

        /**
         * Refresh category trees
         */
        refreshCategories: function () {
            this.$CategoryMap.refresh();
            this.$CategoryMapPrivate.refresh();
        },

        /**
         * Event: onDestroy
         */
        $onDestroy: function () {
            window.PasswordCategories = null;
        }
    });
});