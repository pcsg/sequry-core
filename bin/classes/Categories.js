/**
 * Categories Handler
 * Get information of password categories
 *
 * @module package/sequry/core/bin/classes/Categories
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 */
define('package/sequry/core/bin/classes/Categories', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QUIDOM, QUIAjax) {
    "use strict";

    var pkg = 'sequry/core';

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/sequry/core/bin/classes/Categories',

        /**
         * Get public categories
         *
         * @param {Array} ids - category IDs
         * @returns {Promise}
         */
        getPublic: function (ids) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_passwords_categories_public_get', resolve, {
                    'package': pkg,
                    onError  : reject,
                    ids      : JSON.encode(ids)
                });
            });
        },

        /**
         * Get private categories
         *
         * @param {Array} ids - category IDs
         * @returns {Promise}
         */
        getPrivate: function (ids) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_passwords_categories_private_get', resolve, {
                    'package': pkg,
                    onError  : reject,
                    ids      : JSON.encode(ids)
                });
            });
        },

        /**
         * Set public password categories to multiple passwords at once (requires edit permissions!)
         *
         * @param {Array} passwordIds
         * @param {Array} categoryIds
         * @param {Object} AuthData - Authentication data
         */
        setPublicPasswordsCategories: function (passwordIds, categoryIds, AuthData) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post(
                    'package_sequry_core_ajax_passwords_categories_public_setToPasswords',
                    resolve, {
                        'package'  : pkg,
                        onError    : reject,
                        passwordIds: JSON.encode(passwordIds),
                        categoryIds: JSON.encode(categoryIds),
                        authData   : JSON.encode(AuthData)
                    }
                );
            });
        },

        /**
         * Set private password categories to multiple passwords at once
         *
         * @param {Array} passwordIds
         * @param {Array} categoryIds
         * @return {Promise}
         */
        setPrivatePasswordsCategories: function (passwordIds, categoryIds) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post(
                    'package_sequry_core_ajax_passwords_categories_private_setToPasswords',
                    resolve, {
                        'package'  : pkg,
                        onError    : reject,
                        passwordIds: JSON.encode(passwordIds),
                        categoryIds: JSON.encode(categoryIds)
                    }
                );
            });
        },

        /**
         * Set private categories for a password
         *
         * @param {number} passwordId
         * @param {Array} categoryIds
         * @return {Promise}
         */
        setPrivatePasswordCategories: function (passwordId, categoryIds) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_sequry_core_ajax_passwords_categories_private_setToPassword',
                    resolve, {
                        'package'  : pkg,
                        onError    : reject,
                        passwordId : passwordId,
                        categoryIds: JSON.encode(categoryIds)
                    });
            });
        }
    });
});
