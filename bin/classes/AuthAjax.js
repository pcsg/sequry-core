/**
 * AuthAjax
 *
 * Perform asynchronous requests that require authentication
 *
 * @module package/sequry/core/bin/classes/AuthAjax
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 */
define('package/sequry/core/bin/classes/AuthAjax', [

    'package/sequry/core/bin/Authentication',
    'Ajax'

], function (Authentication, QUIAjax) {
    "use strict";

    var pkg = 'sequry/core';

    return new Class({

        Type: 'package/sequry/core/bin/classes/AuthAjax',

        Binds: [
            'get',
            'post',
            '$execute'
        ],

        /**
         * Execute an authenticated GET request
         *
         * @param {String} func - Ajax function
         * @param {Object} RequestParams - Additional request parameters
         * @returns {Promise}
         */
        get: function (func, RequestParams) {
            return this.$execute('get', func, RequestParams);
        },

        /**
         * Execute an authenticated POST request
         *
         * @param {String} func - Ajax function
         * @param {Object} RequestParams - Additional request parameters
         * @returns {Promise}
         */
        post: function (func, RequestParams) {
            return this.$execute('post', func, RequestParams);
        },

        /**
         * Execute authenticated GET/POST request
         *
         * @param {String} method - either "post" or "get"
         * @param {String} func - Ajax function
         * @param {Object} RequestParams - Additional request parameters
         * @returns {Promise}
         */
        $execute: function (method, func, RequestParams) {
            var self = this;

            return new Promise(function (resolve, reject) {
                var FuncAuthBySecurityClass = function (securityClassIds) {
                    RequestParams = Object.merge({
                            'package': pkg,
                            onError  : reject
                        },
                        RequestParams
                    );

                    Authentication.checkSecurityClassAuthStatus(
                        securityClassIds
                    ).then(function (AuthStatus) {
                        if (AuthStatus.authenticatedAll) {
                            QUIAjax[method](func, resolve, RequestParams);
                            return;
                        }

                        // authenticate for single SecurityClass
                        if (securityClassIds.length === 1) {
                            Authentication.securityClassAuth(
                                securityClassIds[0]
                            ).then(
                                function () {
                                    QUIAjax[method](func, resolve, RequestParams);
                                },
                                reject
                            );

                            return;
                        }

                        // authenticate for multiple SecurityClasses
                        Authentication.multiSecurityClassAuth(securityClassIds).then(function() {
                            QUIAjax[method](func, resolve, RequestParams);
                        }, reject);
                    });
                };

                // If a securityClassId is given, immediately authenticate for it
                if ("securityClassId" in RequestParams) {
                    FuncAuthBySecurityClass([RequestParams.securityClassId]);
                    return;
                }

                // If multiple securityClassIds are given, immediately authenticate for all
                if ("securityClassIds" in RequestParams) {
                    FuncAuthBySecurityClass(RequestParams.securityClassIds);
                    return;
                }

                // If a passwordId is given, get securityClassId first, then authenticate
                if ("passwordId" in RequestParams) {
                    self.$getSecurityClassId(
                        RequestParams.passwordId
                    ).then(function (securityClassId) {
                        FuncAuthBySecurityClass([securityClassId]);
                    });

                    return;
                }

                reject('Please specify securityClassId(s) or passwordId in RequestParams.');
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
                QUIAjax.get('package_sequry_core_ajax_passwords_getSecurityClassId', resolve, {
                    'package' : pkg,
                    onError   : reject,
                    passwordId: passwordId
                });
            });
        }
    });
});
