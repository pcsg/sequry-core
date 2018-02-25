/**
 * AuthAjax
 * Perform asynchronous requests with preceding authentication
 *
 * @module package/sequry/core/bin/Authentication
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require package/sequry/core/bin/classes/AuthAjax
 */
define('package/sequry/core/bin/AuthAjax', [

    'package/sequry/core/bin/classes/AuthAjax'

], function (AuthAjaxClass) {
    "use strict";

    return new AuthAjaxClass();
});
