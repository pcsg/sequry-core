/**
 * Password Handler
 * Create and edit categories
 *
 * @module package/pcsg/grouppasswordmanager/bin/classes/Passwords
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require Ajax
 * @require package/pcsg/grouppasswordmanager/bin/AuthAjax
 */
define('package/pcsg/grouppasswordmanager/bin/classes/Passwords', [

    'Ajax',
    'package/pcsg/grouppasswordmanager/bin/AuthAjax'

], function (QUIAjax, AuthAjax) {
    "use strict";

    var pkg = 'pcsg/grouppasswordmanager';

    return new Class({

        Type: 'package/pcsg/grouppasswordmanager/bin/classes/Passwords',

        /**
         * Search categories
         *
         * @param {Object} SearchParams - grid search parameters
         * @returns {Promise}
         */
        getPasswords: function (SearchParams) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getList', resolve, {
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
         * @returns {Promise}
         */
        get: function (passwordId) {
            return AuthAjax.get('package_pcsg_grouppasswordmanager_ajax_passwords_get', {
                passwordId: passwordId
            });
        },

        /**
         * Get view data of single password object (authentication required!)
         *
         * @param {number} passwordId
         * @returns {Promise}
         */
        getView: function (passwordId) {
            return AuthAjax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getView', {
                passwordId: passwordId
            });
        },

        /**
         * Get all users and groups a password is shared with (authentication required!)
         *
         * @param {number} passwordId
         * @returns {Promise}
         */
        getShareUsersAndGroups: function (passwordId) {
            return AuthAjax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getShareUsersAndGroups', {
                passwordId: passwordId
            });
        },

        /**
         * Get share data of single password object (authentication required!)
         *
         * @param {number} passwordId
         * @returns {Promise}
         */
        getShareData: function (passwordId) {
            return AuthAjax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getShareData', {
                passwordId: passwordId
            });
        },

        /**
         * Set share data of single password object (authentication required!)
         *
         * @param {number} passwordId
         * @param {array} shareData
         * @returns {Promise}
         */
        setShareData: function (passwordId, shareData) {
            return AuthAjax.post('package_pcsg_grouppasswordmanager_ajax_passwords_setShareData', {
                passwordId: passwordId,
                shareData : JSON.encode(shareData)
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
                QUIAjax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getSecurityClassIds', resolve, {
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
                QUIAjax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getAccessInfo', resolve, {
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
                QUIAjax.post('package_pcsg_grouppasswordmanager_ajax_passwords_create', resolve, {
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
         * @returns {Promise}
         */
        editPassword: function (passwordId, PasswordData) {
            return AuthAjax.post('package_pcsg_grouppasswordmanager_ajax_passwords_edit', {
                passwordId  : passwordId,
                passwordData: JSON.encode(PasswordData)
            });
        },

        /**
         * Create a new PasswordLink (authentication required!)
         *
         * @param {number} passwordId - password ID
         * @param {Object} LinkData - PasswordLink data
         * @returns {Promise}
         */
        createLink: function (passwordId, LinkData) {
            return AuthAjax.post('package_pcsg_grouppasswordmanager_ajax_passwords_link_create', {
                passwordId: passwordId,
                linkData  : JSON.encode(LinkData)
            });
        },

        /**
         * Get list of PasswordLinks
         *
         * @param {number} passwordId - password ID
         * @param {Object} LinkData - PasswordLink data
         * @returns {Promise}
         */
        getLinkList: function (passwordId, LinkData) {
            return AuthAjax.get('package_pcsg_grouppasswordmanager_ajax_passwords_link_getList', {
                passwordId: passwordId,
                'package' : 'pcsg/grouppasswordmanager'
            });
        },

        /**
         * Delete a password object
         *
         * @param {number} passwordId - password ID
         * @returns {Promise}
         */
        deletePassword: function (passwordId) {
            return AuthAjax.post('package_pcsg_grouppasswordmanager_ajax_passwords_delete', {
                passwordId: passwordId
            });
        },

        /**
         * Get available password type
         *
         * @returns {Promise}
         */
        getTypes: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_pcsg_grouppasswordmanager_ajax_passwordtypes_getList', resolve, {
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
                QUIAjax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getAvailableAuthPluginsInfo', resolve, {
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
                QUIAjax.post(
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
                QUIAjax.get('package_pcsg_grouppasswordmanager_ajax_passwords_generateRandom', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        }
    });
});
