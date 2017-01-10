/**
 * Actors Handler
 * Register and update new authentication methods for a user
 *
 * @module package/pcsg/grouppasswordmanager/bin/classes/Actors
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 */
define('package/pcsg/grouppasswordmanager/bin/classes/Actors', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QUIDOM, Ajax) {
    "use strict";

    var pkg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/pcsg/grouppasswordmanager/bin/classes/Actors',

        /**
         * Get information for a specific actor
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
         * Set security class to a group
         *
         * @param {number} groupId
         * @param {number} securityClassId
         *
         * @returns {Promise}
         */
        addGroupSecurityClass: function (groupId, securityClassId) {
            return new Promise(function (resolve, reject) {
                Ajax.post('package_pcsg_grouppasswordmanager_ajax_actors_addGroupSecurityClass', resolve, {
                    'package'      : pkg,
                    onError        : reject,
                    groupId        : groupId,
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
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_actors_removeGroupSecurityClass', resolve, {
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
         * @param {object} AuthData - Authentifaction data for all relevant security classes
         *
         * @returns {Promise}
         */
        addUsersToGroup: function (groupId, userIds, AuthData) {
            return new Promise(function (resolve, reject) {
                Ajax.post('package_pcsg_grouppasswordmanager_ajax_actors_addUsersToGroup', resolve, {
                    'package': pkg,
                    onError  : reject,
                    groupId  : groupId,
                    userIds  : JSON.encode(userIds),
                    authData : JSON.encode(AuthData)
                });
            });
        },

        /**
         * Add group(s) to a user
         *
         * @param {number} userId - User ID
         * @param {array} groupIds - IDs of groups that shall be added to the user
         * @param {object} AuthData - Authentifaction data for all relevant security classes
         *
         * @returns {Promise}
         */
        addGroupsToUser: function (userId, groupIds, AuthData) {
            return new Promise(function (resolve, reject) {
                Ajax.post('package_pcsg_grouppasswordmanager_ajax_actors_addGroupsToUser', resolve, {
                    'package': pkg,
                    onError  : reject,
                    userId   : userId,
                    groupIds : JSON.encode(groupIds),
                    authData : JSON.encode(AuthData)
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
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_getAuthPluginSettings', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        }
    });
});
