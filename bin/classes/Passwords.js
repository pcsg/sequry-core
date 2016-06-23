/**
 * Password Handler
 * Create and edit categories
 *
 * @module package/pcsg/grouppasswordmanager/bin/classes/Passwords
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 */
define('package/pcsg/grouppasswordmanager/bin/classes/Passwords', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QUIDOM, Ajax) {
    "use strict";

    var pkg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/pcsg/grouppasswordmanager/bin/classes/Passwords',

        /**
         * Search categories
         *
         * @param {Object} SearchParams - grid search parameters
         * @returns {Promise}
         */
        getPasswords: function (SearchParams) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getList', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    searchParams: JSON.encode(SearchParams)
                });
            });
        },

        /**
         * Get single password object (authentication required!)
         *
         * @param {number} passwordId
         * @param {object} AuthData
         * @returns {*}
         */
        getView: function (passwordId, AuthData) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getView', resolve, {
                    'package' : pkg,
                    onError   : reject,
                    passwordId: passwordId,
                    authData  : JSON.encode(AuthData)
                });
            });
        },

        /**
         * Get unencrypted information of a password
         *
         * @param {number} passwordId
         * @returns {*}
         */
        getPasswordInfo: function (passwordId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getInfo', resolve, {
                    'package' : pkg,
                    onError   : reject,
                    passwordId: passwordId
                });
            });
        },

        /**
         * Create a new password object
         *
         * @param {Object} PasswordData - password data
         * @param {Object} AuthData - Authentication data
         * @returns {Promise}
         */
        createPassword: function (PasswordData, AuthData) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_passwords_create', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    passwordData: JSON.encode(PasswordData),
                    authData    : JSON.encode(AuthData)
                });
            });
        },

        /**
         * Delete a password object
         *
         * @param {number} passwordId - password ID
         * @param {Object} AuthData - Authentication data
         * @returns {Promise}
         */
        deletePassword: function (passwordId, AuthData) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_passwords_delete', resolve, {
                    'package' : pkg,
                    onError   : reject,
                    passwordId: passwordId,
                    authData  : JSON.encode(AuthData)
                });
            });
        }


    });
});
