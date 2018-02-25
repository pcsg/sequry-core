/**
 * Display a password result for QUIQQER Desktop Search
 *
 * @module package/sequry/core/bin/controls/passwords/DesktopSearchResult
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/sequry/core/bin/controls/passwords/DesktopSearchResult', [

    'package/sequry/core/bin/Passwords'

], function (Passwords) {
    "use strict";

    return new Class({

        Type   : 'package/sequry/core/bin/controls/passwords/DesktopSearchResult',

        initialize: function (options) {
            if (!options.passwordId) {
                return;
            }

            Passwords.openPasswordListPanel().then(function(PasswordList) {
                PasswordList.viewPassword(options.passwordId);
            });
        }
    });
});
