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
         * Get all data of single password object (authentication required!)
         *
         * @param {number} passwordId
         * @param {object} AuthData
         * @returns {*}
         */
        get: function (passwordId, AuthData) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_passwords_get', resolve, {
                    'package' : pkg,
                    onError   : reject,
                    passwordId: passwordId,
                    authData  : JSON.encode(AuthData)
                });
            });
        },

        /**
         * Get view data of single password object (authentication required!)
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
         * Get share data of single password object (authentication required!)
         *
         * @param {number} passwordId
         * @param {object} AuthData
         * @returns {*}
         */
        getShareData: function (passwordId, AuthData) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getShareData', resolve, {
                    'package' : pkg,
                    onError   : reject,
                    passwordId: passwordId,
                    authData  : JSON.encode(AuthData)
                });
            });
        },

        /**
         * Set share data of single password object (authentication required!)
         *
         * @param {number} passwordId
         * @param {array} shareData
         * @param {object} AuthData
         * @returns {*}
         */
        setShareData: function (passwordId, shareData, AuthData) {
            return new Promise(function (resolve, reject) {
                Ajax.post('package_pcsg_grouppasswordmanager_ajax_passwords_setShareData', resolve, {
                    'package' : pkg,
                    onError   : reject,
                    passwordId: passwordId,
                    shareData : JSON.encode(shareData),
                    authData  : JSON.encode(AuthData)
                });
            });
        },

        /**
         * Get ID of current security class of a password
         *
         * @param {number} passwordId
         * @returns {number}
         */
        getSecurityClassId: function (passwordId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getSecurityClassId', resolve, {
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
         * @returns {Promise}
         */
        createPassword: function (PasswordData) {
            return new Promise(function (resolve, reject) {
                Ajax.post('package_pcsg_grouppasswordmanager_ajax_passwords_create', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    passwordData: JSON.encode(PasswordData)
                });
            });
        },

        /**
         * Edit a password object (authentication required!)
         *
         * @param {number} passwordId - password ID
         * @param {Object} PasswordData - password data
         * @param {Object} AuthData - Authentication data
         * @returns {Promise}
         */
        editPassword: function (passwordId, PasswordData, AuthData) {
            return new Promise(function (resolve, reject) {
                Ajax.post('package_pcsg_grouppasswordmanager_ajax_passwords_edit', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    passwordId  : passwordId,
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
                Ajax.post('package_pcsg_grouppasswordmanager_ajax_passwords_delete', resolve, {
                    'package' : pkg,
                    onError   : reject,
                    passwordId: passwordId,
                    authData  : JSON.encode(AuthData)
                });
            });
        },

        /**
         * Get available password type
         *
         * @returns {Promise}
         */
        getTypes: function () {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_passwordtypes_getList', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        },

        /**
         * Get IDs of authentication plugins which are availble for accessing a specific password
         *
         * @param {number} passwordId
         * @returns {Promise}
         */
        getAvailableAuthPluginsInfo: function (passwordId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getAvailableAuthPluginsInfo', resolve, {
                    'package' : pkg,
                    onError   : reject,
                    passwordId: passwordId
                });
            });
        }
    });
});
