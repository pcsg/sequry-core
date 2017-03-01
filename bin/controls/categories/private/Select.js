/**
 * Select a private password category
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/categories/private/Select
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/buttons/Select
 * @require qui/controls/loader/Loader
 * @requrie Ajax
 * @require Locale
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/categories/private/Select.css
 *
 * @event onLoaded [this] - fires when security classes are loaded
 * @event onCategorySelect [catId, this] - fires when user selects a category
 */
define('package/pcsg/grouppasswordmanager/bin/controls/categories/private/Select', [

    'qui/controls/windows/Confirm',

    'package/pcsg/grouppasswordmanager/bin/controls/categories/public/Select',
    'package/pcsg/grouppasswordmanager/bin/controls/categories/private/Map',
    'package/pcsg/grouppasswordmanager/bin/Categories',

    'Locale'

], function (QUIConfirm, CategorySelect, CategoryMapPrivate, Categories, QUILocale) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: CategorySelect,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/categories/private/Select',

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

            Categories.getPrivate(this.$categoryIds).then(function (categories) {
                for (var i = 0, len = categories.length; i < len; i++) {
                    var Cat = categories[i];

                    self.$getCatElm(
                        Cat.id,
                        Cat.title
                    ).inject(self.$CatContainer);

                    self.$Categories[Cat.id] = Cat;
                }

                self.Loader.hide();
            });
        },

        /**
         * Get category tree control
         *
         * @return {Object} - CategoryTree
         */
        $getCategoryTreeControl: function () {
            return new CategoryMapPrivate({
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

            Categories.getPrivate(catIds).then(function (categories) {
                for (var i = 0, len = categories.length; i < len; i++) {
                    self.$categoryIds.push(categories[i].id);
                }

                self.$refresh();
            });
        }
    });
});