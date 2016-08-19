/**
 * Authentication Handler
 * Register and update new authentication methods for a user
 *
 * @module package/pcsg/grouppasswordmanager/bin/classes/Authentication
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 */
define('package/pcsg/grouppasswordmanager/bin/classes/Authentication', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QUIDOM, Ajax) {
    "use strict";

    var pkg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/pcsg/grouppasswordmanager/bin/classes/Authentication',

        /**
         * Get all authentication plugins currently installed in the system
         *
         * @returns {Promise}
         */
        getAuthPlugins: function () {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_getAuthPluginList', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        },

        /**
         * Get control to authenticate with a specific authentication plugin
         *
         * @param {number} authPluginId - ID of authentication plugin
         * @returns {Promise}
         */
        getAuthenticationControl: function (authPluginId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_getAuthenticationControl', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    authPluginId: authPluginId
                });
            });
        },

        /**
         * Get control to change authentication information for a specific plugin
         *
         * @param {number} authPluginId - ID of authentication plugin
         * @returns {Promise}
         */
        getChangeAuthenticationControl: function (authPluginId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_getChangeAuthenticationControl', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    authPluginId: authPluginId
                });
            });
        },

        /**
         * Get control to authenticate with a specific authentication plugin
         *
         * @param {number} authPluginId - ID of authentication plugin
         * @returns {Promise}
         */
        getRegistrationControl: function (authPluginId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_getRegistrationControl', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    authPluginId: authPluginId
                });
            });
        },

        /**
         * Get control to authenticate with authentication plugins of a specific
         * security class
         *
         * @param {number} securityClassId - ID of security class
         * @returns {Promise}
         */
        getAuthPluginControlsBySecurityClass: function (securityClassId) {
            return new Promise(function (resolve, reject) {
                Ajax.get(
                    'package_pcsg_grouppasswordmanager_ajax_auth_getControlsBySecurityClass',
                    resolve,
                    {
                        'package'      : pkg,
                        onError        : reject,
                        securityClassId: securityClassId
                    }
                );
            });
        },

        /**
         * Get control to change authentication information for a specific plugin
         *
         * @param {number} authPluginId - ID of authentication plugin
         * @param {string} oldInfo - old (current) authentication information
         * @param {string} newInfo - new authentication information
         * @param {boolean} recovery - change information with help of recovery code if old auth info is lost
         * @returns {Promise}
         */
        changeAuthInformation: function (authPluginId, oldInfo, newInfo, recovery) {

            console.log(recovery);

            return new Promise(function (resolve, reject) {
                Ajax.post('package_pcsg_grouppasswordmanager_ajax_auth_changeAuthenticationInformation', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    authPluginId: authPluginId,
                    oldAuthInfo : oldInfo,
                    newAuthInfo : newInfo,
                    recovery    : recovery ? 1 : 0
                });
            });
        },

        /**
         * Get id, title and description of an authentication plugin
         *
         * @param {number} authPluginId - ID of authentication plugin
         * @returns {Promise}
         */
        getAuthPluginInfo: function (authPluginId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_getAuthPluginInfo', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    authPluginId: authPluginId
                });
            });
        },

        /**
         * Get id, title and description of an authentication plugin
         *
         * @param {number} securityClassId - id of security class
         * @param {Object} AuthData - authentication information
         * @returns {Promise}
         */
        checkAuthInfo: function (securityClassId, AuthData) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_checkAuthInfo', resolve, {
                    'package'      : pkg,
                    onError        : reject,
                    securityClassId: securityClassId,
                    authData       : JSON.encode(AuthData)
                });
            });
        },

        /**
         * Get id, title and description of a security class
         *
         * @param {number} securityClassId - ID of security class
         * @returns {Promise}
         */
        getSecurityClassInfo: function (securityClassId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_getSecurityClassInfo', resolve, {
                    'package'      : pkg,
                    onError        : reject,
                    securityClassId: securityClassId
                });
            });
        },

        /**
         * Get all available security classes
         *
         * @returns {Promise}
         */
        getSecurityClasses: function () {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_getSecurityClassesList', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        },

        /**
         * Create new security class
         *
         * @param {Object} Data
         * @returns {Promise}
         */
        createSecurityClass: function (Data) {
            return new Promise(function (resolve, reject) {
                Ajax.post('package_pcsg_grouppasswordmanager_ajax_auth_createSecurityClass', resolve, {
                    'package': pkg,
                    onError  : reject,
                    data     : JSON.encode(Data)
                });
            });
        },

        /**
         * Edit security class
         *
         * @param {number} id - security class id
         * @param {Object} Data
         * @returns {Promise}
         */
        editSecurityClass: function (id, Data) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_editSecurityClass', resolve, {
                    'package': pkg,
                    onError  : reject,
                    id       : id,
                    data     : JSON.encode(Data)
                });
            });
        },

        /**
         * Delete a security class
         *
         * @param {number} id
         * @returns {Promise}
         */
        deleteSecurityClass: function (id) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_deleteSecurityClass', resolve, {
                    'package': pkg,
                    onError  : reject,
                    id       : id
                });
            });
        },

        /**
         * Delete a security class
         *
         * @param {number} id
         * @param {string} type - "user" / "group"
         * @returns {Promise}
         */
        getActor: function (id, type) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_actors_get', resolve, {
                    'package': pkg,
                    onError  : reject,
                    id       : id,
                    type     : type
                });
            });
        },

        /**
         * Checks if a user has access to passwords which keys are not protected by all possible authentication plugins
         *
         * @param {number} authPluginId - authentication plugin
         * @returns {Promise}
         */
        hasNonFullyAccessiblePasswords: function (authPluginId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_hasNonFullyAccessiblePasswords', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    authPluginId: authPluginId
                });
            });
        },

        /**
         * Get IDs of security classes of passwords which keys are not protected by
         * all possible authentication plugins (current session user)
         *
         * @param {number} authPluginId - authentication plugin
         * @returns {Promise}
         */
        getNonFullyAccessiblePasswordSecurityClassIds: function (authPluginId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_getNonFullyAccessiblePasswordSecurityClassIds', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    authPluginId: authPluginId
                });
            });
        },

        /**
         * Get ID of recovery code for authentication plugin for current session user
         *
         * @param {number} authPluginId - authentication plugin
         * @returns {Promise}
         */
        getRecoveryCodeId: function (authPluginId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_getRecoveryCodeId', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    authPluginId: authPluginId
                });
            });
        }
    });
});
