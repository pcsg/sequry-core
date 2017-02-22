/**
 * Authentication Handler
 * Authenticate for password operations
 *
 * @module package/pcsg/grouppasswordmanager/bin/Authentication
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 */
define('package/pcsg/grouppasswordmanager/bin/Authentication', [

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication'

], function (AuthHandler) {
    "use strict";

    return new AuthHandler();
});
