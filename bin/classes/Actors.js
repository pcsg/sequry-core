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
                Ajax.get('package_pcsg_grouppasswordmanager_ajax_auth_getActor', resolve, {
                    'package': pkg,
                    onError  : reject,
                    id       : id,
                    type     : type
                });
            });
        }
    });
});
