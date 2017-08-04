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
         * Get copy content of a password
         *
         * @param {number} passwordId
         * @param {object} AuthData
         * @returns {Promise}
         */
        getCopyContent: function (passwordId, AuthData) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getCopyContent', resolve, {
                    'package' : pkg,
                    onError   : reject,
                    passwordId: passwordId,
                    authData  : JSON.encode(AuthData)
                });
            });
        },

        /**
         * Get all users and groups a password is shared with (authentication required!)
         *
         * @param {number} passwordId
         * @param {object} AuthData
         * @returns {Promise}
         */
        getShareUsersAndGroups: function (passwordId, AuthData) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getShareUsersAndGroups', resolve, {
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
         * @returns {Promise}
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
         * @returns {Promise}
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
         * Get security class IDs of multiple passwords
         *
         * @param {Array} passwordIds
         * @returns {Promise}
         */
        getSecurityClassIds: function (passwordIds) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getSecurityClassIds', resolve, {
                    'package'  : pkg,
                    onError    : reject,
                    passwordIds: JSON.encode(passwordIds)
                });
            });
        },

        /**
         * Get access info of password
         *
         * @param {number} passwordId
         * @returns {Promise}
         */
        getAccessInfo: function (passwordId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getAccessInfo', resolve, {
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
        },

        /**
         * Set password favorite status
         *
         * @param {number} passwordId
         * @param {bool} status
         * @return {Promise}
         */
        setFavoriteStatus: function (passwordId, status) {
            return new Promise(function (resolve, reject) {
                Ajax.post(
                    'package_pcsg_grouppasswordmanager_ajax_passwords_setFavoriteStatus',
                    resolve, {
                        'package' : pkg,
                        onError   : reject,
                        passwordId: passwordId,
                        status    : status ? 1 : 0
                    }
                );
            });
        },

        /**
         * Opens the password list panel
         *
         * @return {Promise}
         */
        openPasswordListPanel: function () {
            return new Promise(function (resolve) {
                require([
                    'package/pcsg/grouppasswordmanager/bin/controls/passwords/Panel',
                    'utils/Panels'
                ], function (PasswordManager, PanelUtils) {
                    var PasswordManagerPanel = new PasswordManager({
                        events: {
                            onLoaded: function (Panel) {
                                resolve(Panel);
                                window.PasswordList = Panel;
                            }
                        }
                    });

                    PanelUtils.openPanelInTasks(PasswordManagerPanel).then(function (Panel) {
                        Panel.open();
                        Panel.addEvents({
                            onDestroy: function () {
                                window.PasswordList = null;
                            }
                        });

                        if (window.PasswordList) {
                            window.PasswordList = Panel;
                            resolve(window.PasswordList);
                        }
                    });
                });
            });
        },

        /**
         * Generate a random password
         *
         * @returns {Promise}
         */
        generateRandomPassword: function () {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_passwords_generateRandom', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        }
    });
});
