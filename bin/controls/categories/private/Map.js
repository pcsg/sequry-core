/**
 * Password category map (private categories)
 *
 * @module package/sequry/core/bin/controls/categories/private/Map
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require package/sequry/core/bin/controls/categories/public/Map
 * @requrie Ajax
 */
define('package/sequry/core/bin/controls/categories/private/Map', [

    'package/sequry/core/bin/controls/categories/public/Map',

    'Ajax'

], function (CategoryMap, QUIAjax) {
    "use strict";

    return new Class({

        Extends: CategoryMap,
        Type   : 'package/sequry/core/bin/controls/categories/private/Map',

        initialize: function (options) {
            this.parent(options);
            this.$lcKey = 'pcsg-gpm-passwords-categories-toggleCategories-private';
        },

        /**
         * Get all categories
         *
         * @return {Promise}
         */
        $getCategories: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get(
                    'package_sequry_core_ajax_passwords_categories_private_getList',
                    resolve, {
                        'package': 'sequry/core',
                        onError  : reject
                    }
                );
            });
        },

        /**
         * Create new password category
         *
         * @param {number} [parentId] - Parent ID of new category (if omitted create root category)
         * @param {string} title - new category title
         * @return {Promise}
         */
        $addCategory: function (title, parentId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post(
                    'package_sequry_core_ajax_passwords_categories_private_create',
                    resolve, {
                        'package': 'sequry/core',
                        parentId : parentId,
                        title    : title,
                        onError  : reject
                    }
                );
            });
        },

        /**
         * Rename password category
         *
         * @param {number} id - category id
         * @param {string} title - new category title
         * @return {Promise}
         */
        $renameCategory: function (id, title) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post(
                    'package_sequry_core_ajax_passwords_categories_private_rename',
                    resolve, {
                        'package': 'sequry/core',
                        id       : id,
                        title    : title,
                        onError  : reject
                    }
                );
            });
        },

        /**
         * Delete password category
         *
         * @param {integer} id
         * @return {Promise}
         */
        $deleteCategory: function (id) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post(
                    'package_sequry_core_ajax_passwords_categories_private_delete',
                    resolve, {
                        'package': 'sequry/core',
                        id       : id,
                        onError  : reject
                    }
                );
            });
        }
    });
});