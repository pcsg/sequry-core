/**
 * Authentication Handler
 * Register and update new authentication methods for a user
 *
 * @module package/sequry/core/bin/classes/Authentication
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/sequry/core/bin/classes/Authentication', [

    'qui/classes/DOM',
    'Ajax',
    'Locale'

], function (QUIDOM, QUIAjax, QUILocale) {
    "use strict";

    var lg  = 'sequry/core';
    var pkg = 'sequry/core';

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/sequry/core/bin/classes/Authentication',

        /**
         * Authentication with specific authentication plugins
         *
         * @param {String} actionKey
         * @param {Array} authPluginIds
         * @return {Promise}
         */
        actionAuthentication: function (actionKey, authPluginIds) {
            return new Promise(function (resolve, reject) {
                require([
                    'package/sequry/core/bin/controls/auth/Authenticate'
                ], function (AuthWindow) {
                    var Popup = new AuthWindow({
                        authPluginIds: authPluginIds,
                        required     : authPluginIds.length,
                        info         : 'Test', // @todo Locale,
                        events       : {
                            onSubmit: function (AuthData) {
                                Popup.showLoader();

                                QUIAjax.post(
                                    'package_sequry_core_ajax_auth_action_authenticate',
                                    function () {
                                        Popup.close();
                                        resolve();
                                    },
                                    {
                                        package      : pkg,
                                        actionKey    : actionKey,
                                        authPluginIds: JSON.encode(authPluginIds),
                                        authData     : JSON.encode(AuthData),
                                        onError      : function (e) {
                                            if (e.getAttribute('authPluginId')) {
                                                Popup.displayAuthDataRecoveryOption(
                                                    e.getAttribute('authPluginId')
                                                );
                                            }

                                            Popup.hideLoader();
                                        }
                                    }
                                );
                            },
                            onClose : function () {
                                reject();
                                Popup.close();
                            },
                            onAbort : function () {
                                reject();
                                Popup.close();
                            }
                        }
                    });

                    Popup.open();
                });
            });
        },

        /**
         * Authentication for a specific security class
         *
         * @param {number} securityClassId
         * @param {Array} [hideAuthPluginIds] - IDs of authentication plugins that should
         * not be displayed in the authentication window
         * @return {Promise}
         */
        securityClassAuth: function (securityClassId, hideAuthPluginIds) {
            var self = this;

            hideAuthPluginIds = hideAuthPluginIds || [];

            return new Promise(function (resolve, reject) {
                require([
                    'package/sequry/core/bin/controls/auth/Authenticate'
                ], function (AuthWindow) {
                    Promise.all([
                        self.getAuthPluginIdsBySecurityClass([securityClassId]),
                        self.getSecurityClassInfo(securityClassId)
                    ]).then(function (result) {
                        var authPluginIds = result[0][securityClassId];

                        for (var i = 0, len = hideAuthPluginIds.length; i < len; i++) {
                            authPluginIds.erase(hideAuthPluginIds[i]);
                        }

                        var Popup = new AuthWindow({
                            authPluginIds: authPluginIds,
                            required     : result[1].requiredFactors,
                            info         : QUILocale.get(lg,
                                'classes.authentication.securityClassAuth.info', result[1]
                            ),
                            events       : {
                                onSubmit: function (AuthData) {
                                    Popup.showLoader();

                                    self.$authenticate(
                                        securityClassId,
                                        AuthData
                                    ).then(function (success) {
                                        if (!success) {
                                            Popup.hideLoader();
                                            return;
                                        }

                                        Popup.close();
                                        resolve();
                                    }, function (e) {
                                        if (e.getAttribute('authPluginId')) {
                                            Popup.displayAuthDataRecoveryOption(
                                                e.getAttribute('authPluginId')
                                            );
                                        }

                                        Popup.hideLoader();
                                    });
                                },
                                onClose : function () {
                                    reject();
                                    Popup.close();
                                },
                                onAbort : function () {
                                    reject();
                                    Popup.close();
                                }
                            }
                        });

                        Popup.open();
                    });
                });
            });
        },

        /**
         * Authenticate for multiple security classes
         *
         * @param {Array} securityClassIds
         * @param {String} [info] - Info text that is shown in the authentication popup (top)
         * @param {Array} [hideAuthPluginIds] - IDs of authentication plugins that should
         * not be displayed in authentication window
         * @return {Promise}
         */
        multiSecurityClassAuth: function (securityClassIds, info, hideAuthPluginIds) {
            return new Promise(function (resolve, reject) {
                require([
                    'package/sequry/core/bin/controls/auth/MultiSecurityClassAuthWindow'
                ], function (MultiAuthWindow) {
                    new MultiAuthWindow({
                        info             : info ? info : false,
                        securityClassIds : securityClassIds,
                        hideAuthPluginIds: hideAuthPluginIds,
                        events           : {
                            onSubmit: function (Popup) {
                                resolve();
                                Popup.close();
                            },
                            onClose : function () {
                                reject();
                            },
                            onAbort : function (Popup) {
                                reject();
                                Popup.close();
                            }
                        }
                    }).open();
                });
            });
        },

        /**
         * Authenticate for a single SecurityClass
         *
         * @param {Number} securityClassId
         * @param {Object} AuthData
         * @return {Promise}
         */
        $authenticate: function (securityClassId, AuthData) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post(
                    'package_sequry_core_ajax_auth_authenticate',
                    resolve, {
                        'package'      : pkg,
                        authData       : JSON.encode(AuthData),
                        securityClassId: securityClassId,
                        onError        : reject
                    }
                );
            });
        },

        /**
         * Authenticate for all available plugins
         *
         * @return Promise
         */
        authAll: function () {
            return new Promise(function (resolve, reject) {
                require([
                    'package/sequry/core/bin/controls/auth/AuthenticateAll'
                ], function (AllAuth) {
                    var Popup = new AllAuth({
                        events: {
                            onSubmit: function (AuthData) {
                                resolve(AuthData);
                                Popup.close();
                            },
                            onClose : function () {
                                reject();
                                Popup.close();
                            },
                            onAbort : function () {
                                reject();
                                Popup.close();
                            }
                        }
                    });

                    Popup.open();
                });
            });
        },

        /**
         * Get all authentication plugins currently installed in the system
         *
         * @returns {Promise}
         */
        getAuthPlugins: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_auth_getAuthPluginList', resolve, {
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
                QUIAjax.get('package_sequry_core_ajax_auth_getAuthenticationControl', resolve, {
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
                QUIAjax.get('package_sequry_core_ajax_auth_getChangeAuthenticationControl', resolve, {
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
                QUIAjax.get('package_sequry_core_ajax_auth_getRegistrationControl', resolve, {
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
         * @param {Array} authPluginIds - IDs of authentication plugins
         * @returns {Promise}
         */
        getAuthPluginControls: function (authPluginIds) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get(
                    'package_sequry_core_ajax_auth_getControls',
                    resolve,
                    {
                        'package'    : pkg,
                        onError      : reject,
                        authPluginIds: JSON.encode(authPluginIds)
                    }
                );
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
                QUIAjax.get(
                    'package_sequry_core_ajax_auth_getControlsBySecurityClass',
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
         * Get all authentication controls a user has access to
         *
         * @return {Promise}
         */
        getControlsByUser: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get(
                    'package_sequry_core_ajax_auth_getControlsByUser',
                    resolve,
                    {
                        'package': pkg,
                        onError  : reject
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
         * @returns {Promise}
         */
        changeAuthInformation: function (authPluginId, oldInfo, newInfo) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_sequry_core_ajax_auth_changeAuthenticationInformation', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    authPluginId: authPluginId,
                    oldAuthInfo : oldInfo,
                    newAuthInfo : newInfo
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
                QUIAjax.get('package_sequry_core_ajax_auth_getAuthPluginInfo', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    authPluginId: authPluginId
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
                QUIAjax.get('package_sequry_core_ajax_auth_getSecurityClassInfo', resolve, {
                    'package'      : pkg,
                    onError        : reject,
                    securityClassId: securityClassId
                });
            });
        },

        /**
         * Get IDs of authentication plugins for multiple securtity classes
         *
         * @param {Array} securityClassIds - IDs of security class(es)
         * @returns {Promise}
         */
        getAuthPluginIdsBySecurityClass: function (securityClassIds) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_auth_getAuthPluginIdsBySecurityClass', resolve, {
                    'package'       : pkg,
                    onError         : reject,
                    securityClassIds: JSON.encode(securityClassIds)
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
                QUIAjax.get('package_sequry_core_ajax_auth_getSecurityClassesList', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        },

        /**
         * Check authentication status of every auth module of a security class
         *
         * @param {Array} securityClassIds
         * @returns {Promise}
         */
        checkSecurityClassAuthStatus: function (securityClassIds) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_auth_checkSecurityClassAuthStatus', resolve, {
                    'package'       : pkg,
                    onError         : reject,
                    securityClassIds: JSON.encode(securityClassIds)
                });
            });
        },

        /**
         * Check authentication status for multiple authentication plugins
         *
         * @param {Array} authPluginIds
         * @returns {Promise}
         */
        checkAuthStatus: function (authPluginIds) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_auth_checkAuthStatus', resolve, {
                    'package'    : pkg,
                    onError      : reject,
                    authPluginIds: JSON.encode(authPluginIds)
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
                QUIAjax.post('package_sequry_core_ajax_auth_createSecurityClass', resolve, {
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
                QUIAjax.get('package_sequry_core_ajax_auth_editSecurityClass', resolve, {
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
                QUIAjax.get('package_sequry_core_ajax_auth_deleteSecurityClass', resolve, {
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
                QUIAjax.get('package_sequry_core_ajax_actors_get', resolve, {
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
                QUIAjax.get('package_sequry_core_ajax_auth_hasNonFullyAccessiblePasswords', resolve, {
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
        getNonFullyAccessibleSecurityClassIds: function (authPluginId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_auth_getNonFullyAccessibleSecurityClassIds', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    authPluginId: authPluginId
                });
            });
        },

        /**
         * Get ID of recovery code for authentication plugin for current session user
         *
         * @param {number} authPluginId - authentication plugin ID
         * @returns {Promise}
         */
        getRecoveryCodeId: function (authPluginId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_auth_recovery_getCodeId', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    authPluginId: authPluginId
                });
            });
        },

        /**
         * Send recovery token for recovery process of an authentication plugin
         *
         * @param {number} authPluginId - authentication plugin ID
         * @returns {Promise}
         */
        sendRecoveryToken: function (authPluginId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_sequry_core_ajax_auth_recovery_sendToken', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    authPluginId: authPluginId
                });
            });
        },

        /**
         * Re-generate a Recovery Code
         *
         * @param {Number} authPluginId
         * @param {String} authData
         * @returns {Promise}
         */
        regenerateRecoveryCode: function (authPluginId, authData) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_auth_recovery_regenerateCode', resolve, {
                    'package'   : pkg,
                    onError     : reject,
                    authPluginId: authPluginId,
                    authData    : authData
                });
            });
        },

        /**
         * Register a user with an authentication plugin
         *
         * @param {Integer} authPluginId
         * @param {String} registrationData
         * @return {Promise}
         */
        registerUser: function (authPluginId, registrationData) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_sequry_core_ajax_auth_registerUser', resolve, {
                    'package'       : 'sequry/core',
                    onError         : reject,
                    authPluginId    : authPluginId,
                    registrationData: registrationData
                });
            });
        },

        /**
         * Get ID of default authentication plugin
         *
         * @return {Promise}
         */
        getDefaultAuthPluginId: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_auth_getDefaultPluginId', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        },

        /**
         * Checks if the given user is eligible for a security class
         *
         * @param {number} actorId - user or group ID
         * @param {string} actorType - "user" / "group"
         * @param {number} securityClassId
         * @return {Promise}
         */
        isActorEligibleForSecurityClass: function (actorId, actorType, securityClassId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_auth_isActorEligibleForSecurityClass', resolve, {
                    'package'      : pkg,
                    onError        : reject,
                    actorId        : actorId,
                    actorType      : actorType,
                    securityClassId: securityClassId
                });
            });
        },

        /**
         * Get ID of the default security class (if set)
         *
         * @return {Promise}
         */
        getDefaultSecurityClassId: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_auth_getDefaultSecurityClassId', resolve, {
                    'package': pkg,
                    onError  : reject
                });
            });
        },

        /**
         * Get the symmetric key that is used for encryption
         * between frontend and backend for the current session
         *
         * @return {Promise}
         */
        getCommKey: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_sequry_core_ajax_auth_getCommKey',
                    function (keyData) {
                        if (!keyData) {
                            resolve(keyData);
                            return;
                        }

                        resolve(keyData);
                    }, {
                        'package': pkg,
                        onError  : reject
                    });
            });
        }
    });
});
