/**
 * Display a password result for QUIQQER Desktop Search
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/passwords/DesktopSearchResult
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/pcsg/grouppasswordmanager/bin/controls/passwords/DesktopSearchResult', [

    'package/pcsg/grouppasswordmanager/bin/Passwords'

], function (Passwords) {
    "use strict";

    return new Class({

        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/passwords/DesktopSearchResult',

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
