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
        getAuthPluginControl: function (authPluginId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_getControl', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    authPluginId: authPluginId
                });
            });
        },

        /**
         * Get control to authenticate with a specific authentication plugin
         *
         * @param {number} securityClassId - ID of security class
         * @returns {Promise}
         */
        getAuthPluginControlsBySecurityClass: function (securityClassId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_getControlsBySecurityClass', resolve, {
                    'package'      : pkg,
                    onError        : reject,
                    securityClassId: securityClassId
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

        getEligibleUsersBySecurityClass: function (securityClassId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_getEligibleUsers', resolve, {
                    'package'      : pkg,
                    onError        : reject,
                    securityClassId: securityClassId
                });
            });
        },

        getEligibleGroupsBySecurityClass: function (securityClassId) {
            return new Promise(function (resolve, reject) {
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_getEligibleGroups', resolve, {
                    'package'      : pkg,
                    onError        : reject,
                    securityClassId: securityClassId
                });
            });
        }
    });
});
