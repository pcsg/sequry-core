/**
 * Categories Handler
 * Get information of password categories
 *
 * @module package/pcsg/grouppasswordmanager/bin/classes/Categories
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 */
define('package/pcsg/grouppasswordmanager/bin/classes/Categories', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QUIDOM, QUIAjax) {
    "use strict";

    var pkg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/pcsg/grouppasswordmanager/bin/classes/Categories',

        /**
         * Get public categories
         *
         * @param {Array} ids - category IDs
         * @returns {Promise}
         */
        getPublic: function (ids) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_pcsg_grouppasswordmanager_ajax_passwords_categories_public_get', resolve, {
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
                QUIAjax.get('package_pcsg_grouppasswordmanager_ajax_passwords_categories_private_get', resolve, {
                    'package': pkg,
                    onError  : reject,
                    ids      : JSON.encode(ids)
                });
            });
        },

        /**
         * Set public password categories to multiple passwords at once (required edit permissions!)
         *
         * @param passwordIds
         * @param categoryIds
         */
        setPublicPasswordsCategories: function(passwordIds, categoryIds)
        {

        },

        /**
         * Set private password categories to multiple passwords at once
         *
         * @param passwordIds
         * @param categoryIds
         */
        setPrivatePasswordsCategories: function(passwordIds, categoryIds)
        {

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
                QUIAjax.post('package_pcsg_grouppasswordmanager_ajax_passwords_categories_private_setToPassword',
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
