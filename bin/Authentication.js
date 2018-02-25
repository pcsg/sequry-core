/**
 * Authentication Handler
 * Authenticate for password operations
 *
 * @module package/sequry/core/bin/Authentication
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 */
define('package/sequry/core/bin/Authentication', [

    'package/sequry/core/bin/classes/Authentication'

], function (AuthHandler) {
    "use strict";

    return new AuthHandler();
});
