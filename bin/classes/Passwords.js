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
        }
    });
});
