/**
 * AuthAjax
 * Perform asynchronous requests with preceding authentication
 *
 * @module package/pcsg/grouppasswordmanager/bin/Authentication
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require package/pcsg/grouppasswordmanager/bin/classes/AuthAjax
 */
define('package/pcsg/grouppasswordmanager/bin/AuthAjax', [

    'package/pcsg/grouppasswordmanager/bin/classes/AuthAjax'

], function (AuthAjaxClass) {
    "use strict";

    return new AuthAjaxClass();
});
