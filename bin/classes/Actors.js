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
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_actors_addGroupSecurityClass', resolve, {
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
        }
    });
});
