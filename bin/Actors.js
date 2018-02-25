/**
 * Actors Handler
 * Handle password users and groups
 *
 * @module package/sequry/core/bin/Actors
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 */
define('package/sequry/core/bin/Actors', [

    'package/sequry/core/bin/classes/Actors'

], function (ActorsHandler) {
    "use strict";

    return new ActorsHandler();
});
