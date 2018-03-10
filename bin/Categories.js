/**
 * Categories Handler
 * Get information of password categories
 *
 * @module package/sequry/core/bin/Categories
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 */
define('package/sequry/core/bin/Categories', [

    'package/sequry/core/bin/classes/Categories'

], function (CategoriesHandler) {
    "use strict";

    return new CategoriesHandler();
});
