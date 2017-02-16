/**
 * Categories Handler
 * Get information of password categories
 *
 * @module package/pcsg/grouppasswordmanager/bin/Categories
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 */
define('package/pcsg/grouppasswordmanager/bin/Categories', [

    'package/pcsg/grouppasswordmanager/bin/classes/Categories'

], function (CategoriesHandler) {
    "use strict";

    return new CategoriesHandler();
});
