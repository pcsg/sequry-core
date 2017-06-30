/**
 * AuthAjax
 *
 * Perform asynchronous requests that require authentication
 *
 * @module package/pcsg/grouppasswordmanager/bin/classes/AuthAjax
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 */
define('package/pcsg/grouppasswordmanager/bin/classes/AuthAjax', [

    'package/pcsg/grouppasswordmanager/bin/Authentication',
    'Ajax'

], function (Authentication, QUIAjax) {
    "use strict";

    var pkg = 'pcsg/grouppasswordmanager';

    return new Class({

        //Extends: QUIAjax,
        Type   : 'package/pcsg/grouppasswordmanager/bin/classes/AuthAjax',

        /**
         * Execute an authenticated GET request
         *
         * @param {String} func - Ajax function
         * @param {Object} RequestParams - Additional request parameters
         * @returns {Promise}
         */
        get: function (func, RequestParams) {
            var self = this;

            return new Promise(function (resolve, reject) {
                var FuncAuthBySecurityClass = function(securityClassId) {
                    RequestParams = Object.merge(
                        RequestParams, {
                            'package': pkg
                        }
                    );

                    Authentication.checkAuthStatus(
                        securityClassId
                    ).then(function(AuthStatus) {
                        if (AuthStatus.authenticated) {
                            QUIAjax.get(func, resolve, RequestParams);
                            return;
                        }

                        Authentication.securityClassAuth(
                            securityClassId
                        ).then(function() {
                            QUIAjax.get(func, resolve, RequestParams);
                        });
                    });
                };

                // If a securityClassId is given, immediately authenticate for it
                if ("securityClassId" in RequestParams) {
                    FuncAuthBySecurityClass(RequestParams.securityClassId);
                    return;
                }

                // If a passwordId is given, get securityClassId first, then authenticate
                if ("passwordId" in RequestParams) {
                    self.$getSecurityClassId(
                        RequestParams.passwordId
                    ).then(FuncAuthBySecurityClass);

                    return;
                }

                reject('Please specify securityClassId or passwordId in RequestParams.');
            });
        },

        /**
         * Get ID of current security class of a password
         *
         * @param {number} passwordId
         * @returns {Promise}
         */
        $getSecurityClassId: function (passwordId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_pcsg_grouppasswordmanager_ajax_passwords_getSecurityClassId', resolve, {
                    'package' : pkg,
                    onError   : reject,
                    passwordId: passwordId
                });
            });
        }
    });
});
