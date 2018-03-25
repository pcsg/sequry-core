/**
 * Actors Handler
 * Register and update new authentication methods for a user
 *
 * @module package/sequry/core/bin/classes/Actors
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/sequry/core/bin/classes/Actors', [

    'qui/classes/DOM',
    'Ajax',
    'package/sequry/core/bin/AuthAjax'

], function (QUIDOM, QUIAjax, AuthAjax) {
    "use strict";

    var pkg = 'sequry/core';

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/sequry/core/bin/classes/Actors',

        /**
         * Get information for a specific actor
         *
         * @param {number} id
         * @param {string} type - "user" / "group"
         * @returns {Promise}
         */
        getActor: function (id, type) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_actors_get', resolve, {
                    'package': pkg,
                    onError  : reject,
                    id       : id,
                    type     : type
                });
            });
        },

        /**
         * Add SecurityClass to a group
         *
         * @param {number} groupId
         * @param {number} securityClassId
         * @param {number} [userId]
         *
         * @returns {Promise}
         */
        addGroupSecurityClass: function (groupId, securityClassId, userId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_sequry_core_ajax_actors_groups_addSecurityClass', resolve, {
                    'package'      : pkg,
                    onError        : reject,
                    groupId        : groupId,
                    userId         : userId || null,
                    securityClassId: securityClassId
                });
            });
        },

        /**
         * Remove security class from a group
         *
         * @param {number} groupId
         * @param {number} securityClassId
         *
         * @returns {Promise}
         */
        removeGroupSecurityClass: function (groupId, securityClassId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_actors_groups_removeSecurityClass', resolve, {
                    'package'      : pkg,
                    onError        : reject,
                    groupId        : groupId,
                    securityClassId: securityClassId
                });
            });
        },

        /**
         * Add user(s) to a group
         *
         * @param {number} groupId - Group ID
         * @param {array} userIds - IDs of users that shall be added to the group
         *
         * @returns {Promise}
         */
        addUsersToGroup: function (groupId, userIds) {
            var self = this;

            return new Promise(function (resolve, reject) {
                self.getGroupsSecurityClassIds([groupId]).then(function (securityClassIds) {
                    AuthAjax.post(
                        'package_sequry_core_ajax_actors_groups_addUsers', {
                            securityClassIds: securityClassIds,
                            groupId         : groupId,
                            userIds         : JSON.encode(userIds)
                        }
                    ).then(resolve, reject);
                });
            });
        },

        /**
         * Get IDs of all SecurityClasses of given CryptoGroups
         *
         * @param {Array} groupIds
         * @returns {Promise}
         */
        getGroupsSecurityClassIds: function (groupIds) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_actors_getGroupsSecurityClassIds', resolve, {
                    'package': pkg,
                    onError  : reject,
                    groupIds : JSON.encode(groupIds)
                });
            });
        },

        /**
         * Add group(s) to a user
         *
         * @param {number} userId - User ID
         * @param {array} groupIds - IDs of groups that shall be added to the user
         *
         * @returns {Promise}
         */
        addGroupsToUser: function (userId, groupIds) {
            var self = this;

            return new Promise(function (resolve, reject) {
                self.getGroupsSecurityClassIds(groupIds).then(function (securityClassIds) {
                    AuthAjax.post(
                        'package_sequry_core_ajax_actors_addGroupsToUser', {
                            securityClassIds: securityClassIds,
                            userId          : userId,
                            groupIds        : JSON.encode(groupIds)
                        }
                    ).then(resolve, reject);
                });
            });
        },

        /**
         * Get auth plugin settings for current session user
         *
         * @returns {Promise}
         */
        getAuthPluginSettings: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_auth_getAuthPluginSettings', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        },

        /**
         * Re-encrypt all keys a user has access to
         *
         * @param {Object} AuthData - Authentication data for all (registered) auth plugins
         * @return {Promise}
         */
        reEncryptAllKeys: function (AuthData) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_sequry_core_ajax_actors_reEncryptAll', resolve, {
                    'package': pkg,
                    onError  : reject,
                    authData : JSON.encode(AuthData)
                });
            });
        },

        /**
         * Check if the current session user is eligible to user basic
         * password manager functionality
         *
         * @returns {Promise}
         */
        canUsePasswordManager: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_actors_canUsePasswordManager', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        },

        /**
         * Search actors (users or groups)
         *
         * @param {Object} SearchParams
         * @return {Promise}
         */
        search: function (SearchParams) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_actors_search', resolve, {
                    'package'   : pkg,
                    searchParams: JSON.encode(SearchParams),
                    onError     : reject
                });
            });
        },

        /**
         * Get access info of password
         *
         * @param {number} passwordId
         * @returns {Promise}
         */
        getPasswordAccessInfo: function (passwordId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_actors_getPasswordAccessInfo', resolve, {
                    'package' : pkg,
                    onError   : reject,
                    passwordId: passwordId
                });
            });
        },

        /**
         * Add admin user to a group
         *
         * @param {Number} groupId
         * @param {Number} userId
         * @returns {Promise}
         */
        addGroupAdminUser: function (groupId, userId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_sequry_core_ajax_actors_groups_addAdminUser', resolve, {
                    'package': pkg,
                    groupId  : groupId,
                    userId   : userId,
                    onError  : reject
                });
            });
        },

        /**
         * Remove admin user from a group
         *
         * @param {Number} groupId
         * @param {Number} userId
         * @returns {Promise}
         */
        removeGroupAdminUser: function (groupId, userId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_sequry_core_ajax_actors_groups_removeAdminUser', resolve, {
                    'package': pkg,
                    groupId  : groupId,
                    userId   : userId,
                    onError  : reject
                });
            });
        },

        /**
         * Get group administration information for current session user
         *
         * @return {Promise}
         */
        getGroupAdminStatus: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_actors_users_getGroupAdminStatus', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        },

        /**
         * Get group administration information for current session user
         *
         * @param {Object} SearchParams
         * @return {Promise}
         */
        getGroupAdminUnlockList: function (SearchParams) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_actors_groups_getUnlockList', resolve, {
                    'package'   : pkg,
                    searchParams: JSON.encode(SearchParams),
                    onError     : reject
                });
            });
        },

        /**
         * Authorize access for certain users for groups and securityclasses
         *
         * @param {Array} securityClassIds - SecurityClass IDs an authentication is required for
         * @param {Object} unlockRequests
         */
        unlockUsersForGroups: function (securityClassIds, unlockRequests) {
            return new Promise(function (resolve, reject) {
                AuthAjax.post(
                    'package_sequry_core_ajax_actors_groups_unlockUsers', {
                        securityClassIds: securityClassIds,
                        unlockRequests  : JSON.encode(unlockRequests)
                    }
                ).then(resolve, reject);
            });
        }
    });
});
