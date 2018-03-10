/**
 * Password list filters
 *
 * @module package/sequry/core/bin/controls/categories/Filters
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/Control
 * @require qui/controls/sitemap/Item
 * @require Locale
 * @require css!package/sequry/core/bin/controls/categories/Filters.css
 *
 * @event onSelectFilter [filter, this] - fires if the user selects a filter
 * @event onDeselectFilter [filter, this] - fires if the user deselects a filter
 * @event onChange [selectedFilters, this] - fires if the user selects or deselects a filter
 */
define('package/sequry/core/bin/controls/categories/Filters', [

    'qui/controls/Control',
    'qui/controls/sitemap/Item',

    'package/sequry/core/bin/controls/passwordtypes/Select',

    'Locale',

    'css!package/sequry/core/bin/controls/categories/Filters.css'

], function (QUIControl, QUISiteMapItem, PasswordTypesSelect, QUILocale) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/sequry/core/bin/controls/categories/Filters',

        Binds: [
            '$onInject',
            '$filterItemToggle',
            'getSelected',
            '$change'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.$TypeSelect = null;

            this.$filters = [
                'favorites',
                'owned',
                'mostUsed',
                'new'
            ];

            this.$selectedFilters = [];
        },

        /**
         * event on DOMElement creation
         */
        create: function () {
            this.$Elm = new Element('div', {
                'class': 'pcsg-gpm-categories-filters',
                'html' : '<div class="pcsg-gpm-categories-filters-filter"></div>' +
                '<div class="pcsg-gpm-categories-filters-types"></div>'
            });

            return this.$Elm;
        },

        /**
         * Event: onInject
         */
        $onInject: function () {
            var self = this;

            this.$buildFilters();

            this.$TypeSelect = new PasswordTypesSelect({
                menuTop              : true,
                multiple             : true,
                showIcons            : false,
                checkable            : true,
                placeholderText      : 'Passwort-Typen',
                placeholderSelectable: false,
                events               : {
                    onChange: self.$change
                }
            }).inject(
                this.$Elm.getElement(
                    '.pcsg-gpm-categories-filters-types'
                )
            );
        },

        /**
         * Build filter elements
         */
        $buildFilters: function () {
            var filterIcon;

            for (var i = 0, len = this.$filters.length; i < len; i++) {
                var filter = this.$filters[i];

                // icon
                switch (filter) {
                    case 'owned':
                        filterIcon = 'fa fa-user';
                        break;

                    case 'new':
                        filterIcon = 'fa fa-asterisk';
                        break;

                    case 'mostUsed':
                        filterIcon = 'fa fa-list-ol';
                        break;

                    case 'favorites':
                        filterIcon = 'fa fa-star';
                        break;

                    default:
                        filterIcon = 'fa fa-filter';
                }

                new QUISiteMapItem({
                    text       : QUILocale.get(lg,
                        'controls.categories.filters.' + filter
                    ),
                    icon       : filterIcon,
                    contextmenu: false,
                    hasChildren: false,
                    dragable   : false,
                    filter     : filter,
                    events     : {
                        onClick: this.$filterItemToggle
                    }
                }).inject(
                    this.$Elm.getElement(
                        '.pcsg-gpm-categories-filters-filter'
                    )
                );
            }
        },

        /**
         * Toggles SiteMapItem enabled/disabled status
         *
         * @param {Object} Item - qui/controls/sitemap/Item
         */
        $filterItemToggle: function (Item) {
            var Elm    = Item.getElm();
            var status = Elm.getProperty('data-status');
            var filter = Item.getAttribute('filter');

            if (status == 'on') {
                Item.deselect();
                Elm.setProperty('data-status', 'off');

                this.fireEvent('deselectFilter', [filter, this]);
                this.$selectedFilters.erase(filter);
            } else {
                Item.select();
                Elm.setProperty('data-status', 'on');

                this.fireEvent('selectFilter', [filter, this]);
                this.$selectedFilters.push(filter);
            }

            this.$change();
        },

        /**
         * Fire onChange event with selects data
         */
        $change: function () {
            this.fireEvent('change', [{
                filters: this.$selectedFilters,
                types  : this.$TypeSelect.getValue()
            }, this]);
        },

        /**
         * Get all currently selected filters
         *
         * @return {Array}
         */
        getSelected: function () {
            return this.$selectedFilters;
        }
    });
});